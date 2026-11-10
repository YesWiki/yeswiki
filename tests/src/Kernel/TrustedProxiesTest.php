<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Kernel\Service\TrustedProxies;
use YesWiki\Kernel\Service\WikiUrls;

/** Replicas 07: behind a proxy that terminates TLS, the wiki believes the proxy it was told to believe, and nobody else. */
#[CoversClass(TrustedProxies::class)]
#[CoversClass(WikiUrls::class)]
class TrustedProxiesTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        $_SERVER['HTTP_HOST'] = 'pod-3.internal';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'wiki.example.org';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PORT']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        Request::setTrustedProxies([], 0);
    }

    public function testWithNoProxyNamedTheForwardedHeadersAreIgnored(): void
    {
        TrustedProxies::apply([]);
        $request = Request::createFromGlobals();

        $this->assertFalse($request->isSecure());
        $this->assertSame('10.0.0.5', $request->getClientIp(), 'a forged X-Forwarded-For is not believed');
        $this->assertSame('http://pod-3.internal', WikiUrls::rootUrl());
    }

    public function testATrustedProxySaysTheSchemeTheHostAndTheClient(): void
    {
        TrustedProxies::apply(['trusted_proxies' => '10.0.0.0/8, 192.168.0.0/16']);
        $request = Request::createFromGlobals();

        $this->assertTrue($request->isSecure());
        $this->assertSame('203.0.113.9', $request->getClientIp());
        $this->assertSame('https://wiki.example.org', WikiUrls::rootUrl());
    }

    public function testAProxyOutsideTheTrustedRangeIsNotBelieved(): void
    {
        TrustedProxies::apply(['trusted_proxies' => ['192.168.0.0/16']]);
        $request = Request::createFromGlobals();

        $this->assertFalse($request->isSecure());
        $this->assertSame('10.0.0.5', $request->getClientIp());
    }

    public function testWhoeverConnectsCanBeTheProxyInsideACluster(): void
    {
        TrustedProxies::apply(['trusted_proxies' => 'REMOTE_ADDR']);

        $this->assertSame('203.0.113.9', Request::createFromGlobals()->getClientIp());
    }

    public function testOnlyTheNamedHeadersAreBelieved(): void
    {
        TrustedProxies::apply(['trusted_proxies' => '10.0.0.0/8', 'trusted_headers' => 'X-Forwarded-For']);
        $request = Request::createFromGlobals();

        $this->assertSame('203.0.113.9', $request->getClientIp());
        $this->assertFalse($request->isSecure(), 'X-Forwarded-Proto was not named');
        $this->assertSame('pod-3.internal', $request->getHost());
    }

    public function testAnUnknownHeaderIsRefusedByName(): void
    {
        $this->expectExceptionMessage("'X-Real-IP'");
        TrustedProxies::apply(['trusted_proxies' => '10.0.0.0/8', 'trusted_headers' => 'X-Real-IP']);
    }
}
