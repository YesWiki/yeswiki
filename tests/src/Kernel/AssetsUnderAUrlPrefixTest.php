<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\AssetPublisher;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A farm instance's assets, when the wiki is mounted under a URL prefix. */
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

            $page = $this->fetch('/ecto/SomePage');
            $this->assertStringContainsString('text/html', $page['type']);
        });
    }

    /**
     * The published form of the same thing: `cache/assets/{version}/{path}`, which is what a farm instance's pages actually link to, and what ES module imports resolve to on their own.
     */
    public function testAPublishedAssetIsServedThroughThatPrefixToo(): void
    {
        $this->underAPrefix(function (): void {
            foreach (['styles/yw-entries.css' => 'text/css',
                'javascripts/link-panel.js' => 'text/javascript'] as $path => $type) {
                $asset = $this->fetch('/ecto/' . AssetPublisher::PUBLISHED_PREFIX . 'dev/' . $path);
                $this->assertStringContainsString('200', $asset['status'], $this->diag($path, $asset));
                $this->assertStringContainsString($type, $asset['type'], $this->diag($path, $asset));
            }
        });
    }

    /** ...and when it cannot be served, it is a 404 and not a page. */
    public function testAPublishedAssetThatCannotBeServedIsNotAnsweredWithAPage(): void
    {
        $this->underAPrefix(function (): void {
            foreach ([
                'no such file' => '/ecto/' . AssetPublisher::PUBLISHED_PREFIX . 'dev/javascripts/nope.js',
                'not a servable path' => '/ecto/' . AssetPublisher::PUBLISHED_PREFIX . 'dev/private/secret.js',
                'no version at all' => '/ecto/' . AssetPublisher::PUBLISHED_PREFIX . 'yw-core.css',
            ] as $label => $url) {
                $answer = $this->fetch($url);
                $this->assertStringContainsString('404', $answer['status'], $this->diag($label, $answer));
                $this->assertStringNotContainsString('text/html', $answer['type'], $this->diag($label, $answer));
            }
        });
    }

    /**
     * Lay out a farm whose instance is not reachable from the docroot -- the prefix exists only in the URL -- serve it, and run $probe against it.
     */
    private function underAPrefix(\Closure $probe): void
    {
        $this->getWiki();
        $root = sys_get_temp_dir() . '/yeswiki-prefix-test-' . getmypid();
        $instance = $root . '/elsewhere/ecto';

        if (!is_dir($instance) && !mkdir($instance, 0755, true)) {
            $this->markTestSkipped('could not lay out a farm to serve');
        }

        file_put_contents($instance . '/index.php', sprintf(
            "<?php\ndefine('YESWIKI_PROGRAM_DIR', %s);\nputenv('YESWIKI_CONFIG_FILE=' . __DIR__ . '/yeswiki.config.php');\nrequire YESWIKI_PROGRAM_DIR . '/index.php';\n",
            var_export(\YESWIKI_PROGRAM_DIR, true)
        ));

        file_put_contents($root . '/router.php', sprintf(
            "<?php\nif (is_file(__DIR__ . parse_url(\$_SERVER['REQUEST_URI'], PHP_URL_PATH))) { return false; }\nchdir(__DIR__);\nrequire %s;\n",
            var_export(\YESWIKI_PROGRAM_DIR . '/index.php', true)
        ));

        $this->serverLog = $root . '/server.log';

        $server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::PORT, 'router.php'],
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

            $this->waitForPortToClose();
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    private string $serverLog = '';

    /**
     * A label with whatever the served instance said on its way to failing: its log, and the first of whatever it answered with.
     *
     * @param array{status: string, type: string, body: string}|null $answer
     */
    private function diag(string $label, ?array $answer = null): string
    {
        $log = $this->serverLog !== '' && is_file($this->serverLog)
            ? trim((string)file_get_contents($this->serverLog))
            : '';
        if ($log !== '') {
            $label .= "\n--- served instance log ---\n" . $log;
        }
        if ($answer !== null && $answer['body'] !== '') {
            $label .= "\n--- answered with ---\n" . substr($answer['body'], 0, 600);
        }

        return $label;
    }

    /** Block until nothing answers on the port, so the next test starts from a clean one. */
    private function waitForPortToClose(): void
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $probe = @fsockopen('127.0.0.1', self::PORT, $errno, $error, 0.2);
            if ($probe === false) {
                return;
            }
            fclose($probe);
            usleep(100000);
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

    /**
     * @return array{status: string, type: string, body: string}
     */
    private function fetch(string $path): array
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);

        $http_response_header = [];
        $body = @file_get_contents('http://127.0.0.1:' . self::PORT . $path, false, $context);
        $headers = $http_response_header;

        return [
            'status' => (string)($headers[0] ?? ''),
            'type' => implode(' ', array_filter($headers, fn ($h) => stripos($h, 'content-type:') === 0)),

            'body' => (string)$body,
        ];
    }
}
