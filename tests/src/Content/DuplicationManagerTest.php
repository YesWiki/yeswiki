<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Service\DuplicationManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A file named by a duplication request is only fetched from a public http(s) address, under an authorised name.
 */
class DuplicationManagerTest extends YesWikiTestCase
{
    #[DataProvider('refusedProvider')]
    public function testAFileTheWikiMustNotFetchIsRefused(string $url, ?string $because): void
    {
        $duplicationManager = $this->getWiki()->services->get(DuplicationManager::class);
        $before = glob('files/*');

        try {
            $duplicationManager->downloadFile($url, 'SourcePage', 'NewPage');
            $this->fail("{$url} was fetched");
        } catch (\Exception $e) {
            $this->assertStringContainsString($because ?? _t('BAZ_NOT_AUTHORIZED_FILE'), $e->getMessage());
        }
        $this->assertSame($before, glob('files/*'));
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function refusedProvider(): array
    {
        return [
            'a local file' => ['file:///etc/passwd.png', 'Invalid URL'],
            'a local file without extension' => ['file:///etc/passwd', null],
            'the cloud metadata address' => ['http://169.254.169.254/latest/meta-data/x.png', 'private or reserved'],
            'loopback' => ['http://127.0.0.1:1/files/SourcePage_x.png', 'private or reserved'],
            'loopback by name' => ['http://localhost/files/SourcePage_x.png', 'private or reserved'],
            'another protocol' => ['gopher://93.184.216.34/SourcePage_x.png', 'must use HTTP or HTTPS'],
            'a php file' => ['https://93.184.216.34/files/wakka.config.php', null],
            'a hidden file' => ['https://93.184.216.34/files/.htaccess', null],
            'no extension' => ['https://93.184.216.34/latest/meta-data/', null],
        ];
    }

    public function testARequestWithoutFilesIsNotAnError(): void
    {
        $duplicationManager = $this->getWiki()->services->get(DuplicationManager::class);
        $request = new \Symfony\Component\HttpFoundation\Request([], [
            'originalContent' => 'Some content',
            'sourceUrl' => 'https://elsewhere.example/?',
            'originalTag' => 'SourcePage',
            'type' => 'page',
        ]);
        $tag = 'DuplicationWithoutFiles' . bin2hex(random_bytes(3));

        $duplicationManager->importDistantContent($tag, $request);

        $page = $this->getWiki()->services->get(\YesWiki\Content\Service\PageManager::class)->getOne($tag);
        $this->assertNotEmpty($page);
        $this->getWiki()->services->get(\YesWiki\Content\Service\PageManager::class)->deleteOrphaned($tag);
    }

    #[DataProvider('unreachableTagProvider')]
    public function testADuplicateNamedWithCharactersAUrlRefusesIsNotCreated(string $tag): void
    {
        $duplicationManager = $this->getWiki()->services->get(DuplicationManager::class);

        try {
            $duplicationManager->checkPostData(['type' => 'page', 'newTag' => $tag, 'duplicate-action' => 'open']);
            $this->fail("{$tag} was accepted");
        } catch (\Exception $e) {
            $this->assertStringContainsString($tag, $e->getMessage());
            $this->assertStringContainsString(_t('PAGE_TAG_ALLOWED_CHARACTERS'), $e->getMessage());
        }
    }

    #[DataProvider('unreachableTagProvider')]
    public function testAnImportNamedWithCharactersAUrlRefusesIsNotCreated(string $tag): void
    {
        $duplicationManager = $this->getWiki()->services->get(DuplicationManager::class);
        $request = new \Symfony\Component\HttpFoundation\Request([], [
            'originalContent' => 'body',
            'sourceUrl' => 'https://example.org',
            'originalTag' => 'Source',
            'type' => 'page',
        ]);

        try {
            $duplicationManager->importDistantContent($tag, $request);
            $this->fail("{$tag} was accepted");
        } catch (\Exception $e) {
            $this->assertStringContainsString($tag, $e->getMessage());
            $this->assertStringContainsString(_t('PAGE_TAG_ALLOWED_CHARACTERS'), $e->getMessage());
        }
        $this->assertEmpty($this->getWiki()->services->get(\YesWiki\Content\Service\PageManager::class)->getOne($tag));
    }

    /** @return array<string, array{0: string}> */
    public static function unreachableTagProvider(): array
    {
        return [
            'a space' => ['Ma Page'],
            'a trailing space' => ['MaPage '],
            'a slash' => ['Ma/Page'],
            'a quote' => ["L'Atelier"],
        ];
    }

    public function testEveryDuplicationMessageIsTranslatedInFrenchAndEnglish(): void
    {
        $sources = ['src/Content/Service/DuplicationManager.php', 'src/Content/Handler/DuplicateHandler.php', 'templates/handlers/duplicate.twig'];
        preg_match_all("/_t\\('([A-Z_]+)'/", implode('', array_map('file_get_contents', $sources)), $matches);
        foreach (['fr', 'en'] as $lang) {
            $catalogue = require "src/lang/yeswiki_{$lang}.php";
            $missing = array_values(array_diff(array_unique($matches[1]), array_keys($catalogue)));
            $this->assertSame([], $missing, "untranslated in {$lang}");
        }
    }
}
