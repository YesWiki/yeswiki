<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Where the wiki thinks it is reached, before it has a config to tell it. */
class WikiMountPathTest extends YesWikiTestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $server = [];

    protected function setUp(): void
    {
        parent::setUp();
        require_once 'src/Kernel/urlutils.inc.php';
        $this->server = $_SERVER;
        $_SERVER['HTTP_HOST'] = 'example.org';
        unset($_SERVER['HTTPS']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        parent::tearDown();
    }

    private function baseUrl(string $scriptName, string $requestUri): string
    {
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['REQUEST_URI'] = $requestUri;

        return computeBaseURL(true);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function layouts(): array
    {
        return [
            'docroot root' => ['/index.php', '/?PagePrincipale', 'http://example.org/'],
            'root, no rewrite, entry script' => ['/index.php', '/index.php', 'http://example.org/'],
            'subdirectory' => ['/mywiki/index.php', '/mywiki/?PagePrincipale', 'http://example.org/mywiki/'],
            'subdirectory, no trailing slash' => ['/mywiki/index.php', '/mywiki', 'http://example.org/mywiki/'],
            'subdirectory, page URL' => ['/mywiki/index.php', '/mywiki/SomePage', 'http://example.org/mywiki/'],

            'alias' => ['/index.php', '/ecto/', 'http://example.org/ecto/'],
            'alias, entry script' => ['/index.php', '/ecto/index.php', 'http://example.org/ecto/'],
            'alias, several segments deep' => ['/index.php', '/wikis/ecto/', 'http://example.org/wikis/ecto/'],
        ];
    }

    #[DataProvider('layouts')]
    public function testEachWayAWikiIsMounted(string $scriptName, string $requestUri, string $expected): void
    {
        $this->assertSame($expected, $this->baseUrl($scriptName, $requestUri));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pagesAtTheRoot(): array
    {
        return [
            'a page' => ['/index.php', '/SomePage'],
            'a handler' => ['/index.php', '/SomePage/edit'],
            'a page with a query' => ['/index.php', '/SomePage?foo=1'],

            'a router SAPI' => ['/SomePage/edit', '/SomePage/edit'],
        ];
    }

    #[DataProvider('pagesAtTheRoot')]
    public function testAPageNameIsNotAMountPoint(string $scriptName, string $requestUri): void
    {
        $this->assertSame('http://example.org/', $this->baseUrl($scriptName, $requestUri));
    }

    public function testTheSchemeAndHostAreKept(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'lab.example.org:8443';

        $this->assertSame('https://lab.example.org:8443/ecto/', $this->baseUrl('/index.php', '/ecto/'));
    }
}
