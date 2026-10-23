<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Core\Service\DuplicationManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A file named by a duplication request is only fetched from a public http(s) address, under an authorised name.
 */
class DuplicationManagerTest extends YesWikiTestCase
{
    #[DataProvider('refusedProvider')]
    public function testAFileTheWikiMustNotFetchIsRefused(string $url, ?string $because)
    {
        $duplicationManager = self::getWiki()->services->get(DuplicationManager::class);
        $before = glob('files/*');

        try {
            $duplicationManager->downloadFile($url, 'SourcePage', 'NewPage');
            $this->fail("{$url} was fetched");
        } catch (\Exception $e) {
            $this->assertStringContainsString($because ?? _t('BAZ_NOT_AUTHORIZED_FILE'), $e->getMessage());
        }
        $this->assertSame($before, glob('files/*'));
    }

    public static function refusedProvider(): array
    {
        return [
            'a local file' => ['file:///etc/passwd.png', 'Invalid URL'],
            'a local file without extension' => ['file:///etc/passwd', null],
            'the cloud metadata address' => ['http://169.254.169.254/latest/meta-data/x.png', 'private or reserved'],
            'loopback' => ['http://127.0.0.1/files/SourcePage_x.png', 'private or reserved'],
            'loopback by name' => ['http://localhost/files/SourcePage_x.png', 'private or reserved'],
            'another protocol' => ['gopher://93.184.216.34/SourcePage_x.png', 'must use HTTP or HTTPS'],
            'a php file' => ['https://93.184.216.34/files/wakka.config.php', null],
            'a hidden file' => ['https://93.184.216.34/files/.htaccess', null],
            'no extension' => ['https://93.184.216.34/latest/meta-data/', null],
        ];
    }
}
