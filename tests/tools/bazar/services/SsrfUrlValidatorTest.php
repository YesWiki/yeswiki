<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Bazar\Service\SsrfUrlValidator;

require_once 'tools/bazar/services/SsrfUrlValidator.php';

/**
 * The one place that decides which addresses the wiki may fetch on somebody else's behalf.
 */
class SsrfUrlValidatorTest extends TestCase
{
    private const PUBLIC_IP = '93.184.216.34';
    private const WEB = ['http', 'https'];

    private function validator(): SsrfUrlValidator
    {
        return new SsrfUrlValidator();
    }

    #[DataProvider('refusedProvider')]
    public function testAnAddressBehindTheWikiIsRefused(string $url)
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('private or reserved');

        $this->validator()->resolveSafe($url, self::WEB);
    }

    public static function refusedProvider(): array
    {
        return [
            'loopback' => ['http://127.0.0.1:9999/x'],
            'loopback by name' => ['http://localhost/x'],
            'a private range' => ['http://192.168.32.1/x'],
            'another private range' => ['http://10.0.0.1/x'],
            'the carrier-grade range' => ['http://100.64.0.1/x'],
            'the ietf protocol range' => ['http://192.0.0.170/x'],
            'the 6to4 relay anycast range' => ['http://192.88.99.1/x'],
            'the benchmarking range' => ['http://198.18.0.1/x'],
            'multicast' => ['http://224.0.0.1/x'],
            '6to4 wrapping the carrier-grade range' => ['http://[2002:6440:1::]/x'],
            'the cloud metadata address' => ['http://169.254.169.254/latest/meta-data/'],
            'ipv6 loopback' => ['http://[::1]/x'],
            'an ipv6 unique local address' => ['http://[fd00::1]/x'],
            'an ipv6 link-local address' => ['http://[fe80::1]/x'],
            '6to4 wrapping loopback' => ['http://[2002:7f00:1::]/x'],
            'nat64 wrapping loopback' => ['http://[64:ff9b::7f00:1]/x'],
            'ipv4-compatible wrapping loopback' => ['http://[::7f00:1]/x'],
            '6to4 wrapping a private range' => ['http://[2002:c0a8:2001::]/x'],
        ];
    }

    #[DataProvider('schemeProvider')]
    public function testOnlyTheSchemesTheCallerAsksForAreAccepted(string $url, array $schemes, bool $accepted)
    {
        if (!$accepted) {
            $this->expectException(\Exception::class);
        }

        $resolved = $this->validator()->resolveSafe($url, $schemes);

        $this->assertSame([self::PUBLIC_IP => self::PUBLIC_IP], $resolved);
    }

    public static function schemeProvider(): array
    {
        $url = 'https://' . self::PUBLIC_IP . '/x';
        $plain = 'http://' . self::PUBLIC_IP . '/x';

        return [
            'https where only https is allowed' => [$url, ['https'], true],
            'http where only https is allowed' => [$plain, ['https'], false],
            'http where the web is allowed' => [$plain, self::WEB, true],
            'https where the web is allowed' => [$url, self::WEB, true],
        ];
    }

    public function testACallerThatNamesNoSchemeGetsHttpsOnly()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('must use HTTPS');

        $this->validator()->resolveSafe('http://' . self::PUBLIC_IP . '/x');
    }

    public function testTheRequestIsTiedToTheAddressThatWasChecked()
    {
        $options = $this->validator()->curlPin('https://' . self::PUBLIC_IP . '/x', self::WEB);

        $this->assertSame([self::PUBLIC_IP . ':443:' . self::PUBLIC_IP], $options[CURLOPT_RESOLVE]);
        $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
        $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options[CURLOPT_REDIR_PROTOCOLS]);
    }

    public function testNothingIsPinnedForAnAddressThatWasRefused()
    {
        $this->expectException(\Exception::class);

        $this->validator()->curlPin('http://127.0.0.1/x', self::WEB);
    }
}
