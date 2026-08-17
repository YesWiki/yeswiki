<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The search index (ticket 18 / ADR-0015). */
class SearchIndexTest extends YesWikiTestCase
{
    private const TAG = 'SearchIndexTestPage';
    private const OTHER_TAG = 'SearchIndexTestOtherPage';
    private const CAP_TAG_PREFIX = 'SearchIndexTestCapped';

    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->getWiki()->services->get(SearchIndexSchema::class)->exists()) {
            $this->markTestSkipped('no search index on this wiki -- run ./yeswicli migrate');
        }

        $this->removeFixtures();
    }

    private function removeFixtures(): void
    {
        $wiki = $this->getWiki();
        foreach ([self::TAG, self::OTHER_TAG] as $tag) {
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $wiki->services->get(SearchIndexer::class)->delete($tag);
        }
    }

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        foreach ([self::TAG, self::OTHER_TAG] as $tag) {
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $wiki->services->get(SearchIndexer::class)->delete($tag);
        }
    }

    private function savePage(string $tag, string $title, string $content): void
    {
        $this->getWiki()->services->get(PageManager::class)->save($tag, [
            PageBody::TITLE => $title,
            PageBody::CONTENT => $content,
        ], '', true);

        $this->getWiki()->services->get(SearchIndexer::class)->index($tag);
    }

    /**
     * @return array{results: list<array<string, string>>, total: int, capped: bool}
     */
    private function search(string $phrase, ?string $contentType = null): array
    {
        return $this->getWiki()->services->get(SearchIndexQuery::class)->search($phrase, $contentType, 50);
    }

    /** THE regression. */
    public function testEnvelopeKeysAreNotSearchable(): void
    {
        $this->savePage(self::TAG, 'Une page ordinaire', 'du texte parfaitement ordinaire');

        foreach (['form_id', 'created_at', 'updated_at', 'stored_filename'] as $envelopeKey) {
            $found = $this->search($envelopeKey);
            $this->assertSame(
                0,
                $found['total'],
                "searching the envelope key '{$envelopeKey}' must match nothing; it used to match every page in the wiki"
            );
        }
    }

    public function testAPageIsFoundByItsOwnWords(): void
    {
        $this->savePage(self::TAG, 'Le potager collectif', 'un texte qui parle de rhubarbe');

        $found = $this->search('rhubarbe');

        $this->assertSame(1, $found['total']);
        $this->assertSame(self::TAG, $found['results'][0]['tag']);
        $this->assertSame('Le potager collectif', $found['results'][0]['title']);
    }

    /** Prefix matching is what replaces stemming; ADR-0015 accepts it in exchange. */
    public function testAWordIsMatchedFromItsBeginning(): void
    {
        $this->savePage(self::TAG, 'Ateliers', 'nous organisons des ateliers reguliers');

        $this->assertSame(1, $this->search('atelier')['total'], 'atelier must find ateliers');
        $this->assertSame(0, $this->search('teliers')['total'], 'but matching is by prefix, not substring');
    }

    /** Several words are ANDed: all must be present, in any order. */
    public function testEveryWordMustMatch(): void
    {
        $this->savePage(self::TAG, 'Un titre', 'courgette et potiron');
        $this->savePage(self::OTHER_TAG, 'Un autre titre', 'courgette seulement');

        $this->assertSame(2, $this->search('courgette')['total']);
        $this->assertSame(1, $this->search('courgette potiron')['total']);
        $this->assertSame(1, $this->search('potiron courgette')['total'], 'order must not matter');
    }

    /** A Content is one result however many ACL buckets it was indexed as. */
    public function testATotalCountsContentsNotIndexRows(): void
    {
        $this->savePage(self::TAG, 'Betterave', 'betterave betterave betterave');

        $found = $this->search('betterave');

        $this->assertSame(1, $found['total']);
        $this->assertCount(1, $found['results']);
    }

    /** A Content whose only searchable text is its NAME must still be in the index. */
    public function testAContentWithANameButNoTextIsStillIndexed(): void
    {
        $this->savePage(self::TAG, 'Salsifis', '');

        $found = $this->search('salsifis');

        $this->assertSame(1, $found['total'], 'a Content must be findable by its name alone');
        $this->assertSame(self::TAG, $found['results'][0]['tag']);

        $stats = $this->getWiki()->services->get(SearchIndexQuery::class)->contentStats();
        $this->assertGreaterThan(0, $stats['byType']['page']['count'] ?? 0);
    }

    /**
     * contentStats() is what the forms screen reports "N entries" from, so it counts Contents, not index rows -- a Content with restricted fields owns one row per ACL bucket -- and never counts the forms themselves among the Content they describe.
     */
    public function testContentStatsCountsContentsAndIgnoresForms(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);
        $schema = $wiki->services->get(SearchIndexSchema::class);

        $this->savePage(self::TAG, 'Panais', 'un texte sur le panais');

        $db->query(
            "INSERT INTO {$schema->table()}"
            . ' (tag, acl, acl_hash, page_read_acl, owner, content_type, form_id, title, text, updated_at)'
            . " VALUES ('" . self::TAG . "', '@admins', '" . md5('@admins') . "', '', '',"
            . " 'page', '', 'Panais', 'un secret', '2026-01-01 00:00:00')"
        );

        $stats = $wiki->services->get(SearchIndexQuery::class)->contentStats();
        $pagesBefore = $stats['byType']['page']['count'] ?? 0;

        $this->savePage(self::OTHER_TAG, 'Panais bis', 'un autre texte');
        $stats = $wiki->services->get(SearchIndexQuery::class)->contentStats();

        $this->assertSame(
            $pagesBefore + 1,
            $stats['byType']['page']['count'] ?? 0,
            'a Content indexed in two ACL buckets must count once'
        );
        $this->assertArrayNotHasKey('form', $stats['byType'], 'a form is not Content of its own form');
    }

    public function testTheContentTypeFilterNarrows(): void
    {
        $this->savePage(self::TAG, 'Topinambour', 'un texte sur le topinambour');

        $this->assertSame(1, $this->search('topinambour', 'page')['total']);
        $this->assertSame(0, $this->search('topinambour', 'entry')['total']);
        $this->assertSame(0, $this->search('topinambour', 'user')['total']);
    }

    public function testDeletingAContentRemovesItFromResults(): void
    {
        $this->savePage(self::TAG, 'Salsifis', 'un texte sur le salsifis');
        $this->assertSame(1, $this->search('salsifis')['total']);

        $this->getWiki()->services->get(SearchIndexer::class)->delete(self::TAG);

        $this->assertSame(0, $this->search('salsifis')['total']);
    }

    /**
     * A rename fires no page.* event, so without an explicit hook the index keeps answering under the old tag -- every result for the renamed Content would 404.
     */
    public function testRenamingAContentMovesItsIndexRows(): void
    {
        $this->savePage(self::TAG, 'Rutabaga', 'un texte sur le rutabaga');

        $this->getWiki()->services->get(PageManager::class)->renameTag(self::TAG, self::OTHER_TAG);

        $found = $this->search('rutabaga');
        $this->assertSame(1, $found['total']);
        $this->assertSame(self::OTHER_TAG, $found['results'][0]['tag']);
    }

    /** The event wiring: saving a page must QUEUE it, with no explicit call. */
    public function testSavingAPageQueuesItThroughTheEvent(): void
    {
        $this->getWiki()->services->get(PageManager::class)->save(self::TAG, [
            PageBody::TITLE => 'Panais',
            PageBody::CONTENT => 'un texte sur le panais',
        ], '', true);

        $this->assertGreaterThan(
            0,
            $this->getWiki()->services->get(SearchIndexer::class)->pending(),
            'page.created/page.updated must reach SearchIndexSubscriber'
        );

        $this->getWiki()->services->get(SearchIndexer::class)->drain(1000);

        $this->assertSame(1, $this->search('panais')['total']);
    }

    /** Actions are stripped, never run. */
    public function testActionCallsAreStrippedNotRun(): void
    {
        $this->savePage(
            self::TAG,
            'Une page avec des actions',
            "{{entrylist id=\"1\" template=\"zzmarqueurgabarit\"}}\nun mot bien a nous : chicoree"
        );

        $this->assertSame(1, $this->search('chicoree')['total'], 'the prose around an action is indexed');
        $this->assertSame(0, $this->search('zzmarqueurgabarit')['total'], "an action's arguments are not indexed");
    }

    public function testMarkupIsStrippedButLinkWordsSurvive(): void
    {
        $stripped = TextareaField::stripMarkupForIndex(
            "# Un titre\n**gras** et //italique//\n[[PageCible un libelle]]\n{{action param=\"x\"}}\n<b>html</b>"
        );

        $this->assertStringContainsString('Un titre', $stripped);
        $this->assertStringContainsString('gras', $stripped);
        $this->assertStringContainsString('un libelle', $stripped, 'a link label is prose');
        $this->assertStringContainsString('PageCible', $stripped, 'and a link target is often the most searchable word');
        $this->assertStringContainsString('html', $stripped);
        $this->assertStringNotContainsString('{{', $stripped);
        $this->assertStringNotContainsString('<b>', $stripped);
        $this->assertStringNotContainsString('**', $stripped);
    }

    /**
     * A field whose read_access is `*` is public, exactly as one with no read_access is -- `BazarField::canRead()` grants both to everyone.
     */
    public function testAPublicFieldAclSharesTheDefaultBucket(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);
        $schema = $wiki->services->get(SearchIndexSchema::class);

        $buckets = $db->loadAll(
            "SELECT DISTINCT acl FROM {$schema->table()} WHERE acl = '*'"
        );

        $this->assertSame(
            [],
            $buckets,
            "'*' must be normalised to the public bucket rather than stored as a bucket of its own"
        );
    }

    /** The count is exact up to a cap, and says so. */
    public function testAVeryLargeResultSetReportsACappedCount(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);
        $schema = $wiki->services->get(SearchIndexSchema::class);
        $cap = SearchIndexQuery::COUNT_CAP;

        $rows = [];

        for ($i = 0; $i < $cap + 10; $i++) {
            $rows[] = "('" . self::CAP_TAG_PREFIX . $i . "', '', '" . md5('') . "', '*', '',"
                . " 'page', '', 'Capped fixture', 'zzcapmarker', '2026-01-01 00:00:00')";
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            $db->query(
                "INSERT INTO {$schema->table()}"
                . ' (tag, acl, acl_hash, page_read_acl, owner, content_type, form_id, title, text, updated_at)'
                . ' VALUES ' . implode(', ', $chunk)
            );
        }

        try {
            $found = $this->search('zzcapmarker');

            $this->assertTrue($found['capped'], 'a result set past the cap must say it was capped');
            $this->assertSame($cap, $found['total']);

            $deep = $wiki->services->get(SearchIndexQuery::class)->search('zzcapmarker', null, 5, $cap - 2);
            $this->assertCount(5, $deep['results'], 'paging past the cap must still return rows');
        } finally {
            $db->query(
                "DELETE FROM {$schema->table()} WHERE tag LIKE '" . self::CAP_TAG_PREFIX . "%'"
            );
        }
    }

    public function testAnEmptyQueryFindsNothingRatherThanEverything(): void
    {
        $this->assertSame(0, $this->search('')['total']);
        $this->assertSame(0, $this->search('   ')['total']);

        $this->assertSame(0, $this->search('!!! ???')['total']);
    }

    /**
     * FTS5 reads a bare `OR` / `NEAR` as a query operator and raises on a malformed one, which would turn a visitor's search into a 500.
     */
    public function testQueryOperatorsTypedByAVisitorAreNotOperators(): void
    {
        foreach (['OR', 'AND', 'NOT', 'NEAR', '"', "'", '*', '(('] as $hostile) {
            $found = $this->search($hostile);

            $this->assertLessThanOrEqual(
                $found['total'],
                count($found['results']),
                "searching '{$hostile}' must not raise"
            );
        }
    }
}
