<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Admin\Service\ApiService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A routed request that changes something is refused when a browser sent it from another origin. */
class CrossOriginWriteTest extends YesWikiTestCase
{
    private ?Request $previous = null;

    protected function setUp(): void
    {
        parent::setUp();
        $current = $this->getWiki()->services->get(CurrentRequest::class);
        $this->previous = $current->has() ? $current->get() : null;
    }

    protected function tearDown(): void
    {
        if ($this->previous !== null) {
            $this->getWiki()->services->get(CurrentRequest::class)->replace($this->previous);
        }
        parent::tearDown();
    }

    /** @return array<string, array{string, array<string, string>, bool}> */
    public static function requestProvider(): array
    {
        return [
            'cross-site post' => ['POST', ['HTTP_SEC_FETCH_SITE' => 'cross-site'], true],
            'sibling subdomain post' => ['POST', ['HTTP_SEC_FETCH_SITE' => 'same-site'], true],
            'same-origin post' => ['POST', ['HTTP_SEC_FETCH_SITE' => 'same-origin'], false],
            'typed in the address bar' => ['POST', ['HTTP_SEC_FETCH_SITE' => 'none'], false],
            'cross-site get' => ['GET', ['HTTP_SEC_FETCH_SITE' => 'cross-site'], false],
            'bearer token from anywhere' => ['POST', ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_AUTHORIZATION' => 'Bearer abc'], false],
            'old browser, foreign origin' => ['POST', ['HTTP_ORIGIN' => 'https://elsewhere.example'], true],
            'old browser, own origin' => ['POST', ['HTTP_ORIGIN' => 'https://wiki.example'], false],
            'opaque origin' => ['POST', ['HTTP_ORIGIN' => 'null'], true],
            'server to server' => ['POST', [], false],
        ];
    }

    /** @param array<string, string> $server */
    #[DataProvider('requestProvider')]
    public function testCrossOriginWritesAreRecognised(string $method, array $server, bool $refused): void
    {
        $services = $this->getWiki()->services;
        $services->get(CurrentRequest::class)->replace(
            Request::create('https://wiki.example/?api/pages/Somewhere', $method, [], [], [], $server)
        );

        $this->assertSame($refused, $services->get(ApiService::class)->isCrossOriginWrite());
    }
}
