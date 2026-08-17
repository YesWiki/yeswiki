<?php

namespace YesWiki\Test\Search;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 35: `tags=` is an EXACT filter, which is why it exists. */
#[CoversMethod(SearchIndexQuery::class, 'search')]
#[CoversMethod(SearchIndexer::class, 'writeKeywords')]
class SearchByKeywordTest extends YesWikiTestCase
{
    private const TAGGED = 'TestTicket35TaggedPage';
    private const MENTIONS = 'TestTicket35MentionsPage';
    private const KEYWORD = 'ticket35keyword';

    /**
     * @var list<string>
     */
    private array $created = [];

    protected function setUp(): void
    {
        $wiki = $this->getWiki();
        if (!$wiki->services->get(SearchIndexSchema::class)->exists()) {
            $this->markTestSkipped('no search index on this wiki');
        }

        $pageManager = $wiki->services->get(PageManager::class);

        $pageManager->save(self::TAGGED, [
            'content' => 'a page about something else entirely',
            'keywords' => [self::KEYWORD, 'anotherkeyword'],
        ], '', true);

        $pageManager->save(self::MENTIONS, [
            'content' => 'this page merely says ' . self::KEYWORD . ' in passing',
        ], '', true);

        $this->created = [self::TAGGED, self::MENTIONS];

        $indexer = $wiki->services->get(SearchIndexer::class);
        $indexer->enqueue($this->created);
        $indexer->drain(50);
    }

    protected function tearDown(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $indexer = $wiki->services->get(SearchIndexer::class);
        foreach ($this->created as $tag) {
            $indexer->delete($tag);
            $pageManager->deleteOrphaned($tag);
        }
        $this->created = [];
    }

    private function query(): SearchIndexQuery
    {
        return $this->getWiki()->services->get(SearchIndexQuery::class);
    }

    /**
     * @param array{results: list<array<string, mixed>>, total: int, capped: bool} $found
     *
     * @return list<string>
     */
    private function tagsOf(array $found): array
    {
        return array_column($found['results'], 'tag');
    }

    public function testTheKeywordFilterFindsOnlyTheTaggedPage(): void
    {
        $found = $this->query()->search('', null, 50, 0, [self::KEYWORD]);

        $this->assertContains(self::TAGGED, $this->tagsOf($found));
        $this->assertNotContains(
            self::MENTIONS,
            $this->tagsOf($found),
            'a page that merely mentions the word does not carry the keyword -- this is the whole '
            . 'difference between a tag filter and a text search'
        );
    }

    /** The contrast, asserted rather than assumed: free text does match the mention. */
    public function testAFreeTextSearchForTheSameWordFindsBoth(): void
    {
        $tags = $this->tagsOf($this->query()->search(self::KEYWORD, null, 50, 0));

        $this->assertContains(self::MENTIONS, $tags, 'free text matches the prose');
        $this->assertContains(self::TAGGED, $tags, 'and the keyword is in the indexed text too');
    }

    /** Several keywords mean ALL of them, not any. */
    public function testSeveralKeywordsAreAnded(): void
    {
        $both = $this->tagsOf($this->query()->search('', null, 50, 0, [self::KEYWORD, 'anotherkeyword']));
        $this->assertContains(self::TAGGED, $both);

        $missing = $this->tagsOf($this->query()->search('', null, 50, 0, [self::KEYWORD, 'akeywordnobodyhas']));
        $this->assertSame([], $missing, 'asking for two keywords means Content carrying both');
    }

    /** A blank search box must not list the wiki. */
    public function testAskingForNothingReturnsNothing(): void
    {
        $this->assertSame(0, $this->query()->search('', null, 50, 0, [])['total']);
        $this->assertSame(0, $this->query()->search('', null, 50, 0, ['   '])['total']);
    }

    public function testAnUnknownKeywordMatchesNothing(): void
    {
        $this->assertSame(0, $this->query()->search('', null, 50, 0, ['no-such-keyword-anywhere'])['total']);
    }

    /** Removing a page must take its keyword rows with it, or it keeps answering the filter. */
    public function testKeywordRowsGoWhenTheContentIsDeleted(): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(SearchIndexer::class)->delete(self::TAGGED);

        $this->assertNotContains(
            self::TAGGED,
            $this->tagsOf($this->query()->search('', null, 50, 0, [self::KEYWORD]))
        );

        $dbService = $wiki->services->get(DbService::class);
        $keywords = $dbService->quoteIdentifier($wiki->services->get(SearchIndexSchema::class)->keywordsTable());
        $remaining = $dbService->loadSingle(
            "SELECT COUNT(*) AS c FROM {$keywords} WHERE tag = ?",
            [self::TAGGED]
        );
        $this->assertNotNull($remaining);
        $this->assertSame(0, (int)$remaining['c'], 'the keyword rows must be gone, not orphaned');
    }
}
