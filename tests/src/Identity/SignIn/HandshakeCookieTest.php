<?php

namespace YesWiki\Test\Identity\SignIn;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Service\SignIn\HandshakeCookie;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Identity\SignIn\Contract\HandshakeMode;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\SignedTokens;
use YesWiki\Kernel\Service\WikiCookies;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class HandshakeCookieTest extends YesWikiTestCase
{
    private HandshakeCookie $cookie;

    /** @var array<string, mixed> */
    private array $cookiesWere;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookiesWere = $_COOKIE;
        unset($_COOKIE[HandshakeCookie::NAME]);
        $this->cookie = self::getWiki()->services->get(HandshakeCookie::class);
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->cookiesWere;
        parent::tearDown();
    }

    private function handshake(int $expires): Handshake
    {
        $handshake = new Handshake('acme', '/back', 'https://w/callback', HandshakeMode::Real, $expires);
        $handshake->set('state', 'abc');

        return $handshake;
    }

    private function requestWith(string $value): Request
    {
        return new Request(cookies: [HandshakeCookie::NAME => $value]);
    }

    /** @param array<string, mixed> $params */
    private function withConfig(array $params, string $baseUrl): void
    {
        $current = new CurrentRequest();
        $current->replace(Request::create($baseUrl));
        $cookies = new WikiCookies(new ParameterBag($params + ['base_url' => $baseUrl]), $current);
        $this->cookie = new HandshakeCookie(self::getWiki()->services->get(SignedTokens::class), $cookies);
    }

    public function testIssuedCookieIsReadBackAndATamperedOneIsNot(): void
    {
        $value = $this->cookie->encode($this->handshake(expires: 2000));
        $this->assertSame('acme', $this->cookie->read($this->requestWith($value), 1000)?->providerId);
        $this->assertNull($this->cookie->read($this->requestWith($value . 'x'), 1000));
        $this->assertNull($this->cookie->read($this->requestWith($value), 2001));
    }

    public function testEverythingTheHandshakeRemembersComesBack(): void
    {
        $read = $this->cookie->read($this->requestWith($this->cookie->encode($this->handshake(expires: 2000))), 1000);
        $this->assertNotNull($read);

        $this->assertSame('/back', $read->returnUrl);
        $this->assertSame('https://w/callback', $read->callbackUrl);
        $this->assertSame(HandshakeMode::Real, $read->mode);
        $this->assertSame(2000, $read->expiresAt);
        $this->assertSame('abc', $read->get('state'));
    }

    public function testAPayloadSignedForAnotherPurposeIsRefused(): void
    {
        $tokens = self::getWiki()->services->get(SignedTokens::class);
        [$payload] = explode('.', $this->cookie->encode($this->handshake(expires: 2000)));

        $this->assertNull($this->cookie->read($this->requestWith($payload . '.' . $tokens->sign('yw-auth:' . $payload)), 1000), 'a signature over the same bytes for another cookie');
        $this->assertNull($this->cookie->read($this->requestWith($payload), 1000), 'no signature at all');
    }

    public function testASignedValueThatIsNotAHandshakeIsRefused(): void
    {
        $tokens = self::getWiki()->services->get(SignedTokens::class);
        $payload = rtrim(strtr(base64_encode('"just a string"'), '+/', '-_'), '=');

        $this->assertNull($this->cookie->read($this->requestWith($payload . '.' . $tokens->sign(HandshakeCookie::NAME . ':' . $payload)), 1000));
    }

    public function testNoCookieReadsAsNothing(): void
    {
        $this->assertNull($this->cookie->read(new Request(), 1000));
    }

    public function testSameSiteIgnoresTheWikiSetting(): void
    {
        $this->withConfig(['cookies_samesite' => 'Strict'], 'https://w/');
        $options = $this->cookie->options(1000);
        $this->assertSame('None', $options['samesite']);
        $this->assertTrue($options['secure']);
        $this->withConfig(['cookies_samesite' => 'None'], 'http://w/');
        $this->assertSame('Lax', $this->cookie->options(1000)['samesite']);
    }

    public function testTheOtherOptionsAreTheWikis(): void
    {
        $this->withConfig(['cookie_path' => '/wiki/'], 'https://w/wiki/');
        $options = $this->cookie->options(1234);

        $this->assertSame(1234, $options['expires']);
        $this->assertSame('/wiki/', $options['path']);
        $this->assertTrue($options['httponly']);
    }

    public function testIssuingAndClearingAreSeenInTheSameRequest(): void
    {
        $handshake = $this->handshake(expires: time() + HandshakeCookie::LIFETIME);

        $this->cookie->issue($handshake);
        $this->assertSame($this->cookie->encode($handshake), $_COOKIE[HandshakeCookie::NAME] ?? null);

        $this->cookie->clear();
        $this->assertArrayNotHasKey(HandshakeCookie::NAME, $_COOKIE);
    }
}
