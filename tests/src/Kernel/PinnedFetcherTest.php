<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Service\PinnedFetcher;
use YesWiki\Kernel\Service\SsrfUrlValidator;

/**
 * A fetch on somebody else's behalf checks every address it is sent to, redirects included, and brings back nothing it should not.
 */
class PinnedFetcherTest extends TestCase
{
    /** @var resource|null */
    private static $server;
    private static int $port = 0;
    private static string $root = '';

    public static function setUpBeforeClass(): void
    {
        self::$root = sys_get_temp_dir() . '/yeswiki-pinned-fetcher-' . bin2hex(random_bytes(4));
        mkdir(self::$root);
        file_put_contents(self::$root . '/router.php', <<<'PHP'
            <?php
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            switch ($path) {
                case '/ok':
                    echo 'hello';
                    break;
                case '/empty':
                    break;
                case '/missing':
                    http_response_code(404);
                    echo 'not here';
                    break;
                case '/big':
                    echo str_repeat('x', 4096);
                    break;
                case '/hop':
                    header('Location: /ok', true, 302);
                    break;
                case '/loop':
                    header('Location: /loop', true, 302);
                    break;
                case '/elsewhere':
                    header('Location: http://127.0.0.1:1/ok', true, 302);
                    break;
                case '/metadata':
                    header('Location: http://169.254.169.254/latest/meta-data/', true, 302);
                    break;
                case '/headers':
                    echo $_SERVER['HTTP_X_SECRET'] ?? 'none';
                    break;
            }
            PHP);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            self::fail('no free port to serve the test pages on');
        }
        self::$port = (int)substr((string)stream_socket_get_name($socket, false), strlen('127.0.0.1:'));
        fclose($socket);
        $server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, self::$root . '/router.php'],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes
        );
        if ($server === false) {
            self::fail('the test server did not start');
        }
        self::$server = $server;
        for ($try = 0; $try < 50; $try++) {
            $probe = @fsockopen('127.0.0.1', self::$port);
            if ($probe !== false) {
                fclose($probe);
                break;
            }
            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        array_map('unlink', glob(self::$root . '/*') ?: []);
        @rmdir(self::$root);
    }

    /** A fetcher for which the test server is the wiki's own address, the one loopback origin it may reach. */
    private function fetcher(): PinnedFetcher
    {
        return new PinnedFetcher(new SsrfUrlValidator(new ParameterBag(['base_url' => $this->url('/?')])));
    }

    private function url(string $path): string
    {
        return 'http://127.0.0.1:' . self::$port . $path;
    }

    public function testTheBodyIsReturned(): void
    {
        $this->assertSame('hello', $this->fetcher()->fetch($this->url('/ok')));
    }

    public function testARedirectIsFollowedOnceItsAddressIsChecked(): void
    {
        $this->assertSame('hello', $this->fetcher()->fetch($this->url('/hop')));
    }

    /** @param array{maxBytes?: int, maxRedirects?: int} $options */
    #[DataProvider('refusedProvider')]
    public function testWhatTheWikiMustNotBringBackIsRefused(string $path, string $because, array $options = []): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($because);

        $this->fetcher()->fetch($this->url($path), $options);
    }

    /** @return array<string, array{0: string, 1: string, 2?: array<string, mixed>}> */
    public static function refusedProvider(): array
    {
        return [
            'a redirect to another loopback port' => ['/elsewhere', 'private or reserved'],
            'a redirect to the cloud metadata address' => ['/metadata', 'private or reserved'],
            'a redirect when none is allowed' => ['/hop', 'Too many redirects', ['maxRedirects' => 0]],
            'a redirect loop' => ['/loop', 'Too many redirects'],
            'an http error' => ['/missing', 'HTTP 404'],
            'an empty body' => ['/empty', 'Nothing to get'],
            'a body over the limit' => ['/big', 'Error getting content', ['maxBytes' => 1024]],
        ];
    }

    public function testAStreamStartsAtTheBeginningOfTheBody(): void
    {
        $body = $this->fetcher()->stream($this->url('/ok'));

        $this->assertSame('hello', stream_get_contents($body));
        fclose($body);
    }

    public function testRequestHeadersReachTheOriginFirstAsked(): void
    {
        $this->assertSame('kept', $this->fetcher()->fetch($this->url('/headers'), ['headers' => ['X-Secret: kept']]));
    }
}
