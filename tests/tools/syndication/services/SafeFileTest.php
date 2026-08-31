<?php

namespace YesWiki\Test\Syndication\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Bazar\Service\SsrfUrlValidator;
use YesWiki\Syndication\Service\SafeFile;

require_once 'tools/syndication/vendor/autoload.php';
require_once 'tools/bazar/services/SsrfUrlValidator.php';
require_once 'tools/syndication/services/SafeFile.php';

/**
 * A feed URL is only fetched once the address behind it has been checked and pinned.
 */
class SafeFileTest extends TestCase
{
    private const PUBLIC_IP = '93.184.216.34';

    #[DataProvider('refusedProvider')]
    public function testAnAddressTheWikiMustNotReachIsRefused(string $url, string $because)
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($because);

        SafeFile::pin($url);
    }

    public static function refusedProvider(): array
    {
        return [
            'loopback' => ['http://127.0.0.1:9999/feed.xml', 'private or reserved'],
            'loopback by name' => ['http://localhost/feed.xml', 'private or reserved'],
            'ipv6 loopback' => ['http://[::1]/feed.xml', 'private or reserved'],
            'a private range' => ['http://192.168.32.1:9999/feed.xml', 'private or reserved'],
            'another private range' => ['https://10.1.2.3/feed.xml', 'private or reserved'],
            'the cloud metadata address' => ['http://169.254.169.254/latest/meta-data/', 'private or reserved'],
            'a local file' => ['file:///etc/passwd', 'Invalid URL'],
            'another protocol' => ['gopher://93.184.216.34/x', 'must use HTTP or HTTPS'],
            'nothing that parses' => ['not a url', 'Invalid URL'],
        ];
    }

    #[DataProvider('pinnedProvider')]
    public function testAPublicAddressIsPinnedToWhatWasChecked(string $url, string $expected)
    {
        $options = SafeFile::pin($url);

        $this->assertSame([$expected], $options[CURLOPT_RESOLVE]);
        $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options[CURLOPT_REDIR_PROTOCOLS]);
    }

    public static function pinnedProvider(): array
    {
        return [
            'https on its default port' => ['https://' . self::PUBLIC_IP . '/feed.xml', self::PUBLIC_IP . ':443:' . self::PUBLIC_IP],
            'http on its default port' => ['http://' . self::PUBLIC_IP . '/feed.xml', self::PUBLIC_IP . ':80:' . self::PUBLIC_IP],
            'an explicit port' => ['http://' . self::PUBLIC_IP . ':8080/feed.xml', self::PUBLIC_IP . ':8080:' . self::PUBLIC_IP],
        ];
    }

    public function testTheStricterCallersStillOnlyGetHttps()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('must use HTTPS');

        (new SsrfUrlValidator())->resolveSafe('http://' . self::PUBLIC_IP . '/actor');
    }

    public function testAFeedMayBeServedOverPlainHttp()
    {
        $resolved = (new SsrfUrlValidator())->resolveSafe('http://' . self::PUBLIC_IP . '/feed.xml', SafeFile::SCHEMES);

        $this->assertSame([self::PUBLIC_IP => self::PUBLIC_IP], $resolved);
    }
}
