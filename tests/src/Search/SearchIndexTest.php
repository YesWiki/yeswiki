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

/**
 * The search index (ticket 18 / ADR-0015).
 *
 * The defect this whole ticket exists for: since ticket 09 every body is JSON, so the old
 * `body LIKE '%phrase%'` matched the envelope's own keys -- a search for `title`, `content`,
 * `form_id`, `status` or `keywords` returned **every page in the wiki**, and read to a user
 * as "search is bad" rather than as a bug. testEnvelopeKeysAreNotSearchable is the
 * regression test for it, and the reason the others exist is to keep the replacement honest.
 */
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
        // each test starts from no fixture at all: several of them assert an exact total,
        // and a page left behind by the previous test is indistinguishable from a bug
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
        // saving dispatches page.created/page.updated, which the subscriber indexes on --
        // but index() explicitly too, so a failure here reads as "the indexer is broken"
        // rather than as "the event never fired" (which has its own test below)
        $this->getWiki()->services->get(SearchIndexer::class)->index($tag);
    }

    /** @return array{results: list<array<string, string>>, total: int, capped: bool} */
    private function search(string $phrase, ?string $contentType = null): array
    {
        return $this->getWiki()->services->get(SearchIndexQuery::class)->search($phrase, $contentType, 50);
    }

    /**
     * THE regression. Every one of these words is a key of the JSON envelope, so the old
     * `body LIKE` search matched every row in `pages` for each of them.
     */
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

    /**
     * A Content is one result however many ACL buckets it was indexed as. Getting this
     * wrong would inflate every count on a wiki with restricted fields -- which is precisely
     * the exactness this design is for.
     */
    public function testATotalCountsContentsNotIndexRows(): void
    {
        $this->savePage(self::TAG, 'Betterave', 'betterave betterave betterave');

        $found = $this->search('betterave');

        $this->assertSame(1, $found['total']);
        $this->assertCount(1, $found['results']);
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
     * A rename fires no page.* event, so without an explicit hook the index keeps answering
     * under the old tag -- every result for the renamed Content would 404.
     */
    public function testRenamingAContentMovesItsIndexRows(): void
    {
        $this->savePage(self::TAG, 'Rutabaga', 'un texte sur le rutabaga');

        $this->getWiki()->services->get(PageManager::class)->renameTag(self::TAG, self::OTHER_TAG);

        $found = $this->search('rutabaga');
        $this->assertSame(1, $found['total']);
        $this->assertSame(self::OTHER_TAG, $found['results'][0]['tag']);
    }

    /**
     * The event wiring: saving a page must QUEUE it, with no explicit call.
     *
     * Queued rather than indexed, deliberately -- see SearchIndexSubscriber. Indexing inline
     * would mean resolving a form from inside the service that is mid-write, and
     * FormManager::create() saves the page row before writing the type triple that makes it a
     * form. The drain here stands in for the end-of-request flush the subscriber registers.
     */
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

    /**
     * Actions are stripped, never run. Rendering to index would fold every listed entry into
     * the page's own document, make the result depend on who rendered it, and amount to
     * arbitrary code execution on a background reindex.
     */
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
     * A field whose read_access is `*` is public, exactly as one with no read_access is --
     * `BazarField::canRead()` grants both to everyone. They must therefore share one ACL
     * bucket.
     *
     * Found by benchmarking rather than by reasoning: keeping them apart splits a Content's
     * text across two rows for no reason, and it silently defeated SearchIndexQuery's
     * single-bucket fast path on every real wiki, because the seeded Annuaire and Agenda
     * forms ship `"read_access":"*"` on every field. A 500k-row corpus with no restricted
     * field anywhere was taking the expensive GROUP BY path.
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

    /**
     * The count is exact up to a cap, and says so.
     *
     * Counting every match cannot stop at LIMIT, so on a large corpus a broad query would
     * spend a full pass producing a number nobody reads (measured at ~1.7s over 500k rows).
     * What must NOT change is that results are never dropped -- paging past the cap still
     * returns the right rows.
     */
    public function testAVeryLargeResultSetReportsACappedCount(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);
        $schema = $wiki->services->get(SearchIndexSchema::class);
        $cap = SearchIndexQuery::COUNT_CAP;

        $rows = [];
        // a handful MORE than the cap, so that paging past it has somewhere to go
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

            // and the cap is on the reported NUMBER, not on what can be reached
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
        // punctuation alone reduces to no terms at all
        $this->assertSame(0, $this->search('!!! ???')['total']);
    }

    /**
     * FTS5 reads a bare `OR` / `NEAR` as a query operator and raises on a malformed one,
     * which would turn a visitor's search into a 500. Terms are quoted for that reason.
     */
    public function testQueryOperatorsTypedByAVisitorAreNotOperators(): void
    {
        foreach (['OR', 'AND', 'NOT', 'NEAR', '"', "'", '*', '(('] as $hostile) {
            $found = $this->search($hostile);
            // the assertion that matters is that the call returned at all -- a raised FTS5
            // parse error is a 500 on a visitor's search box. The coherence check keeps it
            // from being a bare "no exception" test.
            $this->assertLessThanOrEqual(
                $found['total'],
                count($found['results']),
                "searching '{$hostile}' must not raise"
            );
        }
    }
}
