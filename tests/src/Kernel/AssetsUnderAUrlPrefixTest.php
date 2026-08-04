<?php

namespace YesWiki\Test\Kernel;

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

        $server = proc_open(
            sprintf('%s -S 127.0.0.1:%d router.php', escapeshellarg(PHP_BINARY), self::PORT),
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $root
        );
        if (!is_resource($server)) {
            $this->markTestSkipped('could not start a web server to ask');
        }

        try {
            $this->waitForServer();

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
        } finally {
            proc_terminate($server);
            proc_close($server);
            exec('rm -rf ' . escapeshellarg($root));
        }
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
