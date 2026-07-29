<?php

namespace YesWiki\Test\Content\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\DiffService;
use YesWiki\Content\Service\PageManager;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 09 rewrote `pages.body` for **every** revision, not just `latest`. That decision
 * is what these tests defend: the alternative -- converting current revisions and reading
 * old ones through a normalization branch -- was rejected, so every consumer of history
 * has to see the one shape, and a revision written before the change has to read back
 * exactly as it was written.
 *
 * The cases here are the ones where being wrong loses a user's data rather than just
 * rendering oddly: reverting to an old revision, diffing across revisions, and the
 * round-trip of text that JSON encoding is liable to mangle.
 */
class PageHistoryShapeTest extends YesWikiTestCase
{
    private const TAG = 'PageHistoryShapeRegressionPage';

    /** Markup a naive JSON round trip is liable to mangle. */
    private const TRICKY = "# Titre accentué\n\n{{nav links=\"A, B\" titles=\"S'inscrire\"}}\n\nA \\ backslash, a \"quote\", and a tab:\tdone.";

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(PageManager::class)->deleteOrphaned(self::TAG);
        parent::tearDown();
    }

    /**
     * The core promise. Three revisions, each read back by its own timestamp, each
     * byte-identical to what was saved.
     */
    public function testEveryRevisionReadsBackExactlyAsItWasSaved(): void
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);
        $bodies = ['first revision', self::TRICKY, 'third revision'];

        foreach ($bodies as $body) {
            $pageManager->save(self::TAG, [PageBody::CONTENT => $body], '', true);
            // `time` has second granularity, so distinct revisions need distinct seconds
            sleep(1);
        }

        $revisions = $pageManager->getRevisions(self::TAG);
        $this->assertCount(3, $revisions, 'each save must produce its own revision');

        // getRevisions() is newest-first
        foreach (array_reverse($revisions) as $index => $revision) {
            $stored = $pageManager->getById($revision['id'], true);
            $this->assertIsArray($stored);
            $this->assertSame(
                $bodies[$index],
                PageBody::content($stored['body']),
                "revision {$index} did not survive the round trip"
            );
        }
    }

    public function testReadingAnOldRevisionByTimeReturnsThatRevisionsContent(): void
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);

        $pageManager->save(self::TAG, [PageBody::CONTENT => 'the old wording'], '', true);
        $old = $pageManager->getOne(self::TAG);
        $this->assertIsArray($old);
        sleep(1);
        $pageManager->save(self::TAG, [PageBody::CONTENT => 'the new wording'], '', true);

        $current = $pageManager->getOne(self::TAG);
        $this->assertIsArray($current);
        $this->assertSame('the new wording', PageBody::content($current['body']));
        $atOldTime = $pageManager->getOne(self::TAG, $old['time']);
        $this->assertIsArray($atOldTime);
        $this->assertSame(
            'the old wording',
            PageBody::content($atOldTime['body']),
            '?time= must read that revision, not the latest'
        );
    }

    public function testGetPreviousRevisionReturnsTheEarlierBody(): void
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);

        $pageManager->save(self::TAG, [PageBody::CONTENT => 'earlier'], '', true);
        sleep(1);
        $pageManager->save(self::TAG, [PageBody::CONTENT => 'later'], '', true);

        $current = $pageManager->getOne(self::TAG);
        $this->assertIsArray($current);
        $previous = $pageManager->getPreviousRevision($current);
        $this->assertIsArray($previous);

        $this->assertSame('earlier', PageBody::content($previous['body']));
    }

    /**
     * Reverting reads an old revision and writes it forward as a new one -- the path
     * where a shape mismatch would persist corruption rather than just display it.
     */
    public function testRevertingRestoresTheOldContentVerbatim(): void
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);

        $pageManager->save(self::TAG, [PageBody::CONTENT => self::TRICKY], '', true);
        $original = $pageManager->getOne(self::TAG);
        $this->assertIsArray($original);
        sleep(1);
        $pageManager->save(self::TAG, [PageBody::CONTENT => 'replaced'], '', true);

        $pageManager->revertToRevision(self::TAG, $original['id']);

        $reverted = $pageManager->getOne(self::TAG);
        $this->assertIsArray($reverted);
        $this->assertSame(self::TRICKY, PageBody::content($reverted['body']));
    }

    /**
     * A page's other body attributes are not prose, and a revert restores them too --
     * they are part of what the user thinks of as "the page at that revision".
     */
    public function testRevertingRestoresKeywordsAlongWithContent(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $tagsManager = $wiki->services->get(TagsManager::class);

        $pageManager->save(self::TAG, [PageBody::CONTENT => 'with keywords'], '', true);
        $tagsManager->save(self::TAG, 'alpha,beta');
        $withKeywords = $pageManager->getOne(self::TAG);
        $this->assertIsArray($withKeywords);
        $this->assertSame(['alpha', 'beta'], TagsManager::keywordsOf($withKeywords));

        sleep(1);
        $tagsManager->save(self::TAG, 'gamma');
        $this->assertSame(['gamma'], TagsManager::keywordsOf($pageManager->getOne(self::TAG)));

        $pageManager->revertToRevision(self::TAG, $withKeywords['id']);

        $this->assertSame(['alpha', 'beta'], TagsManager::keywordsOf($pageManager->getOne(self::TAG)));
    }

    /**
     * A no-op save must stay a no-op. The check used to be a string comparison; on JSON
     * that would invent a revision every time a body was re-encoded with its keys in
     * another order.
     */
    public function testSavingAnUnchangedBodyCreatesNoRevision(): void
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);

        $pageManager->save(self::TAG, [PageBody::CONTENT => 'stable', PageBody::KEYWORDS => ['a', 'b']], '', true);
        $before = $pageManager->countRevisions(self::TAG);

        // same data, keys in a different order
        $pageManager->save(self::TAG, [PageBody::KEYWORDS => ['a', 'b'], PageBody::CONTENT => 'stable'], '', true);

        $this->assertSame($before, $pageManager->countRevisions(self::TAG), 'key order must not create a revision');
    }

    /**
     * The diff between two revisions is the visible half of history. It must show the
     * prose that changed, not the JSON container it now travels in.
     */
    public function testDiffBetweenRevisionsShowsTheProseNotTheJsonContainer(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $diffService = $wiki->services->get(DiffService::class);

        $pageManager->save(self::TAG, [PageBody::CONTENT => 'the quick brown fox'], '', true);
        $old = $pageManager->getOne(self::TAG);
        sleep(1);
        $pageManager->save(self::TAG, [PageBody::CONTENT => 'the quick red fox'], '', true);
        $new = $pageManager->getOne(self::TAG);
        $this->assertIsArray($old);
        $this->assertIsArray($new);

        $diff = $diffService->getPageDiff($old, $new, false);

        $this->assertStringContainsString('red', $diff);
        $this->assertStringNotContainsString('{"content"', $diff, 'the diff must not expose the JSON container');
    }
}
