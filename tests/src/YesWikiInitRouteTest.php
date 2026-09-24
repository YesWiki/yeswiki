<?php

namespace YesWiki\Test\Core;

use YesWiki\Core\YesWikiInit;

require_once 'tests/YesWikiTestCase.php';

/** In worker mode one YesWikiInit routes every request, so a route must never survive into the next one (ADR-0024). */
class YesWikiInitRouteTest extends YesWikiTestCase
{
    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $get;

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $this->get = $_GET;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = $this->get;
        parent::tearDown();
    }

    public function testTheRootAfterAPageIsTheRootAgain(): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        $this->route($init, '/?ParcoursA/edit');
        $this->assertSame(['ParcoursA', 'edit'], [$init->page, $init->method]);

        $this->route($init, '/');
        $this->assertSame(['', ''], [$init->page, $init->method], 'the previous request must not answer for this one');
    }

    public function testTheWorkerScriptIsNotAPageName(): void
    {
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();

        $this->route($init, '/worker.php');

        $this->assertSame('', $init->page);
    }

    private function route(YesWikiInit $init, string $requestUri): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = $requestUri;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $init->getRoute();
    }
}
