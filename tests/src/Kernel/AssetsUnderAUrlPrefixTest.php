<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\AssetPublisher;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A farm instance's assets, when the wiki is mounted under a URL prefix.
 *
 * An instance's docroot holds `index.php` and its data folders and nothing else: the
 * styles, the themes and the sprites live in the shared source, and `AssetPublisher`
 * serves them from there when the rewrite falls through. That works when the prefix in the
 * URL matches something on disk -- `SCRIPT_NAME` naming the instance's own `index.php`, or
 * a folder the walk can step into.
 *
 * It did not work when the prefix is only a *URL*: an nginx `location`, an Apache `alias`,
 * a proxy mounting the wiki at `/ecto`. Nothing on disk is called `ecto`, so the prefix
 * could not be stripped, the path was not recognised as an asset, and the request fell
 * through to the wiki -- which answered a request for a stylesheet with an HTML page
 * saying it had no handler by that name. Reported from a real server, reproduced here.
 *
 * Driven through a real web server, because every input that decides this (`REQUEST_URI`,
 * `SCRIPT_NAME`, the working directory) is the web server's to set -- and because
 * `interceptAssetRequest()` returns immediately under CLI, so nothing about it can be
 * tested in-process.
 */
class AssetsUnderAUrlPrefixTest extends YesWikiTestCase
{
    private const PORT = 8791;

    public function testAnAssetIsServedThroughAPrefixThatIsNotAFolder(): void
    {
        $this->underAPrefix(function (): void {
            $asset = $this->fetch('/ecto/themes/yeswiki/styles/yeswiki.css');
            $this->assertStringContainsString('200', $asset['status'], 'the stylesheet must be served');
            $this->assertStringContainsString(
                'text/css',
                $asset['type'],
                'served as a stylesheet, not as the wiki page it fell through to'
            );

            // ...and a real page URL under the same prefix still reaches the wiki: this
            // must recognise assets, not swallow everything with a prefix on it
            $page = $this->fetch('/ecto/SomePage');
            $this->assertStringContainsString('text/html', $page['type']);
        });
    }

    /**
     * The published form of the same thing: `cache/assets/{version}/{path}`, which is what a
     * farm instance's pages actually link to, and what ES module imports resolve to on their
     * own. These reach PHP whenever the file has not been materialized into the instance yet
     * -- every first request, and every request for a module the page did not itself declare.
     */
    public function testAPublishedAssetIsServedThroughThatPrefixToo(): void
    {
        $this->underAPrefix(function (): void {
            foreach (['styles/bazar/entries/index.css' => 'text/css',
                'javascripts/link-panel.js' => 'text/javascript'] as $path => $type) {
                $asset = $this->fetch('/ecto/' . AssetPublisher::PUBLISHED_PREFIX . 'dev/' . $path);
                $this->assertStringContainsString('200', $asset['status'], $this->diag($path));
                $this->assertStringContainsString($type, $asset['type'], $this->diag($path));
            }
        });
    }

    /**
     * ...and when it cannot be served, it is a 404 and not a page.
     *
     * A wiki that answers a stylesheet request with HTML gets "bloquée en raison d'un type
     * MIME text/html incorrect" in the console -- which names the symptom and hides the
     * cause. Nothing under `cache/assets/` is ever a page, so say so.
     */
    public function testAPublishedAssetThatCannotBeServedIsNotAnsweredWithAPage(): void
    {
        $this->underAPrefix(function (): void {
            foreach ([
                'no such file' => '/ecto/' . AssetPublisher::PUBLISHED_PREFIX . 'dev/javascripts/nope.js',
                'not a servable path' => '/ecto/' . AssetPublisher::PUBLISHED_PREFIX . 'dev/private/secret.js',
                'no version at all' => '/ecto/' . AssetPublisher::PUBLISHED_PREFIX . 'yw-core.css',
            ] as $label => $url) {
                $answer = $this->fetch($url);
                $this->assertStringContainsString('404', $answer['status'], $this->diag($label));
                $this->assertStringNotContainsString('text/html', $answer['type'], $this->diag($label));
            }
        });
    }

    /**
     * Lay out a farm whose instance is not reachable from the docroot -- the prefix exists
     * only in the URL -- serve it, and run $probe against it.
     */
    private function underAPrefix(\Closure $probe): void
    {
        $this->getWiki(); // for the path constants
        $root = sys_get_temp_dir() . '/yeswiki-prefix-test-' . getmypid();
        $instance = $root . '/elsewhere/ecto';

        if (!is_dir($instance) && !mkdir($instance, 0755, true)) {
            $this->markTestSkipped('could not lay out a farm to serve');
        }

        // the instance: index.php pointing at these sources, exactly what
        // `core:create-instance` writes
        file_put_contents($instance . '/index.php', sprintf(
            "<?php\ndefine('YESWIKI_SOURCE_DIR', %s);\nputenv('YESWIKI_CONFIG_FILE=' . __DIR__ . '/yeswiki.config.php');\nrequire YESWIKI_SOURCE_DIR . '/index.php';\n",
            var_export(\YESWIKI_SOURCE_DIR, true)
        ));

        // a docroot that knows nothing of `ecto`: whatever it cannot serve as a file goes
        // to the shared index.php, which is the arrangement that broke
        file_put_contents($root . '/router.php', sprintf(
            "<?php\nif (is_file(__DIR__ . parse_url(\$_SERVER['REQUEST_URI'], PHP_URL_PATH))) { return false; }\nchdir(__DIR__);\nrequire %s;\n",
            var_export(\YESWIKI_SOURCE_DIR . '/index.php', true)
        ));

        // Kept, not discarded to /dev/null: when this instance answers 500 the reason is a
        // PHP fatal that only its own stderr records, and throwing that away is what made a
        // CI failure here unactionable -- the workflow dumps the *main* server's log, which
        // knows nothing of this one. Surfaced by diag() in the assertion messages below.
        $this->serverLog = $root . '/server.log';
        $server = proc_open(
            sprintf('%s -S 127.0.0.1:%d router.php', escapeshellarg(PHP_BINARY), self::PORT),
            [1 => ['file', $this->serverLog, 'w'], 2 => ['file', $this->serverLog, 'a']],
            $pipes,
            $root
        );
        if (!is_resource($server)) {
            $this->markTestSkipped('could not start a web server to ask');
        }

        try {
            $this->waitForServer();
            $probe();
        } finally {
            proc_terminate($server);
            proc_close($server);
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    private string $serverLog = '';

    /** A label with whatever the served instance said on its way to failing. */
    private function diag(string $label): string
    {
        $log = $this->serverLog !== '' && is_file($this->serverLog)
            ? trim((string)file_get_contents($this->serverLog))
            : '';

        return $log === '' ? $label : $label . "\n--- served instance log ---\n" . $log;
    }

    private function waitForServer(): void
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $probe = @fsockopen('127.0.0.1', self::PORT, $errno, $error, 0.2);
            if ($probe !== false) {
                fclose($probe);

                return;
            }
            usleep(100000);
        }
        $this->markTestSkipped('the web server never came up');
    }

    /** @return array{status: string, type: string} */
    private function fetch(string $path): array
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        // PHP fills $http_response_header in this scope as a side effect of the fetch
        $http_response_header = [];
        @file_get_contents('http://127.0.0.1:' . self::PORT . $path, false, $context);
        $headers = $http_response_header;

        return [
            'status' => (string)($headers[0] ?? ''),
            'type' => implode(' ', array_filter($headers, fn ($h) => stripos($h, 'content-type:') === 0)),
        ];
    }
}
