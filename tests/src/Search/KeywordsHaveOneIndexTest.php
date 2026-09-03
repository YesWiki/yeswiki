<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * One index answers every keyword question, and it covers what `triples` never did (ticket 62).
 *
 * The keyword on a **bazar entry** is the case worth a test of its own: it lives under whatever
 * name the webmaster gave the tags field, not under `body.keywords`, and it is what the old
 * `TagsManager::reindexAll()` -- pages only -- could never see.
 */
class KeywordsHaveOneIndexTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'OneKeywordIndexPage';
    private const PAGE_KEYWORD = 'oneindexpagekeyword';
    private const ENTRY_KEYWORD = 'oneindexentrykeyword';

    private static ?string $formId = null;
    private static ?string $entryTag = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->getWiki()->services->get(SearchIndexSchema::class)->exists()) {
            $this->markTestSkipped('no search index on this wiki -- run ./yeswicli migrate');
        }
    }

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();
        if (!$wiki->services->get(SearchIndexSchema::class)->exists()) {
            return;
        }

        $wiki->services->get(PageManager::class)->save(self::PAGE_TAG, [
            PageBody::CONTENT => 'a page with a keyword',
            PageBody::KEYWORDS => [self::PAGE_KEYWORD],
        ], '', true);

        $formManager = $wiki->services->get(FormManager::class);
        $id = 9300;
        while ($formManager->getOne((string)$id) !== null) {
            $id++;
        }
        self::$formId = (string)$id;
        $formManager->create([
            'id' => self::$formId,
            'label' => 'One keyword index form',
            'description' => '',
            'template' => [
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'tags', 'name' => 'bf_motscles', 'label' => 'Mots cles'],
            ],
        ]);

        $entry = $wiki->services->get(EntryManager::class)->create(self::$formId, [
            'form_id' => self::$formId,
            'antispam' => 1,
            'bf_titre' => 'Une fiche avec un mot cle',
            'bf_motscles' => self::ENTRY_KEYWORD,
        ]);
        self::$entryTag = (string)($entry['tag'] ?? '');

        $wiki->services->get(SearchIndexer::class)->drain(1000);
    }

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        if (self::$entryTag !== null && self::$entryTag !== '') {
            try {
                $wiki->services->get(EntryManager::class)->delete(self::$entryTag);
            } catch (\Throwable $ignored) {
            }
        }
        if (self::$formId !== null) {
            try {
                $wiki->services->get(FormManager::class)->delete(self::$formId);
            } catch (\Throwable $ignored) {
            }
        }
        $wiki->services->get(PageManager::class)->deleteOrphaned(self::PAGE_TAG);
        $wiki->services->get(SearchIndexer::class)->delete(self::PAGE_TAG);
    }

    private function tagsManager(): TagsManager
    {
        return $this->getWiki()->services->get(TagsManager::class);
    }

    public function testNoKeywordTripleIsLeftInTheDatabase(): void
    {
        $dbService = $this->getWiki()->services->get(DbService::class);

        $this->assertSame(
            0,
            (int)$dbService->scalar(
                'SELECT COUNT(*) FROM ' . $dbService->prefixTable('triples') . ' WHERE property = ?',
                0,
                ['http://outils-reseaux.org/_vocabulary/tag']
            )
        );
    }

    /** Autocomplete, the tag cloud and `{{listpagestag}}` all read the one index. */
    public function testAPageKeywordIsFoundEveryWayItIsAskedFor(): void
    {
        $this->assertContains(self::PAGE_KEYWORD, $this->tagsManager()->search(self::PAGE_KEYWORD)['tags']);
        $this->assertContains(self::PAGE_KEYWORD, array_column($this->tagsManager()->getAll(), 'value'));
        $this->assertContains(
            ['keyword' => self::PAGE_KEYWORD, 'tag' => self::PAGE_TAG],
            $this->tagsManager()->pairs([self::PAGE_KEYWORD])
        );
        $this->assertContains(
            self::PAGE_TAG,
            array_column($this->tagsManager()->getPagesByTags(self::PAGE_KEYWORD), 'tag')
        );
    }

    /** The half that was invisible before: a keyword on an entry. */
    public function testAnEntryKeywordIsFoundEveryWayItIsAskedFor(): void
    {
        $this->assertNotSame('', (string)self::$entryTag, 'the fixture entry was not created');

        $this->assertContains(self::ENTRY_KEYWORD, $this->tagsManager()->search(self::ENTRY_KEYWORD)['tags']);
        $this->assertContains(self::ENTRY_KEYWORD, array_column($this->tagsManager()->getAll(), 'value'));
        $this->assertContains(
            ['keyword' => self::ENTRY_KEYWORD, 'tag' => (string)self::$entryTag],
            $this->tagsManager()->pairs([self::ENTRY_KEYWORD])
        );
        $this->assertContains(
            self::ENTRY_KEYWORD,
            array_column($this->tagsManager()->mostUsed(500), 'value')
        );
    }

    /** Removing a keyword edits the body, and the index follows from the truth. */
    public function testRemovingAPairEditsTheBody(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $tag = self::PAGE_TAG . 'Removable';
        $pageManager->save($tag, [
            PageBody::CONTENT => 'to be stripped',
            PageBody::KEYWORDS => ['keptkeyword', 'removedkeyword'],
        ], '', true);
        $wiki->services->get(SearchIndexer::class)->drain(1000);

        try {
            $this->tagsManager()->remove([['keyword' => 'removedkeyword', 'tag' => $tag]]);
            $wiki->services->get(SearchIndexer::class)->index($tag);

            $this->assertSame(['keptkeyword'], TagsManager::keywordsOf($pageManager->getOne($tag, null, false, true)));
            $this->assertSame([], $this->tagsManager()->pairs(['removedkeyword']));
        } finally {
            $pageManager->deleteOrphaned($tag);
            $wiki->services->get(SearchIndexer::class)->delete($tag);
        }
    }

    /** A duplicate carries its keywords in its body, with nothing copying an index row by hand. */
    public function testDuplicatingAPageCarriesItsKeywords(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $authentication = $wiki->services->get(\YesWiki\Identity\Service\AuthenticationService::class);
        $copy = self::PAGE_TAG . 'Copy';

        if (empty($authentication->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->setTag(self::PAGE_TAG);
        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->setPage($pageManager->getOne(self::PAGE_TAG));

        try {
            $wiki->services->get(\YesWiki\Content\Service\DuplicationManager::class)->duplicateLocally([
                'type' => 'page',
                'originalTag' => self::PAGE_TAG,
                'newTag' => $copy,
            ]);
            $wiki->services->get(SearchIndexer::class)->drain(1000);

            $this->assertSame([self::PAGE_KEYWORD], TagsManager::keywordsOf($pageManager->getOne($copy, null, false, true)));
            $this->assertContains(
                ['keyword' => self::PAGE_KEYWORD, 'tag' => $copy],
                $this->tagsManager()->pairs([self::PAGE_KEYWORD])
            );
        } finally {
            $pageManager->deleteOrphaned($copy);
            $wiki->services->get(SearchIndexer::class)->delete($copy);
            $authentication->logout();
        }
    }

    /** A rebuild restores every keyword from the bodies, which is what makes the index a cache. */
    public function testARebuildRestoresTheKeywordsFromTheBodies(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $keywords = $wiki->services->get(SearchIndexSchema::class)->keywordsTable();

        $dbService->query("DELETE FROM {$keywords} WHERE tag = ?", [self::PAGE_TAG]);
        $this->assertSame([], $this->tagsManager()->pairs([self::PAGE_KEYWORD]));

        $wiki->services->get(SearchIndexer::class)->index(self::PAGE_TAG);

        $this->assertContains(
            ['keyword' => self::PAGE_KEYWORD, 'tag' => self::PAGE_TAG],
            $this->tagsManager()->pairs([self::PAGE_KEYWORD])
        );
    }
}
