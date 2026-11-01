<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Core\Service\DuplicationManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A duplication only creates a page its URL can reach, and only fetches files from public http(s) addresses.
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

    #[DataProvider('unreachableTagProvider')]
    public function testADuplicateNamedWithCharactersAUrlRefusesIsNotCreated(string $tag)
    {
        $duplicationManager = self::getWiki()->services->get(DuplicationManager::class);

        try {
            $duplicationManager->checkPostData(['type' => 'page', 'newTag' => $tag, 'duplicate-action' => 'open']);
            $this->fail("{$tag} was accepted");
        } catch (\Exception $e) {
            $this->assertStringStartsWith(_t('INVALID_PAGE_TAG', ['tag' => $tag]), $e->getMessage());
        }
    }

    #[DataProvider('unreachableTagProvider')]
    public function testAnImportNamedWithCharactersAUrlRefusesIsNotCreated(string $tag)
    {
        $duplicationManager = self::getWiki()->services->get(DuplicationManager::class);
        $request = new Request([], [
            'originalContent' => 'body', 'sourceUrl' => 'https://example.org', 'originalTag' => 'Source', 'type' => 'page',
        ]);

        try {
            $duplicationManager->importDistantContent($tag, $request);
            $this->fail("{$tag} was accepted");
        } catch (\Exception $e) {
            $this->assertStringStartsWith(_t('INVALID_PAGE_TAG', ['tag' => $tag]), $e->getMessage());
        }
    }

    public static function unreachableTagProvider(): array
    {
        return [
            'a space' => ['Ma Page'],
            'a trailing space' => ['MaPage '],
            'a slash' => ['Ma/Page'],
            'a quote' => ["L'Atelier"],
        ];
    }
}
