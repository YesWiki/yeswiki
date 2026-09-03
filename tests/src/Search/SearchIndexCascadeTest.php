<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The form cascade (ticket 18 / ADR-0015). */
class SearchIndexCascadeTest extends YesWikiTestCase
{
    /**
     * The tags this class's fixtures actually take.
     *
     * The form is created with a label, and the entry with a title, so both rows are named from
     * what they say rather than from any prefix a test chose -- which is why a sweep over
     * `SearchIndexCascadeEntry%` matched nothing at all and left seventy of each behind, until a
     * hundred and forty rows sharing two titles started failing an unrelated sort test.
     */
    private const FORM_LABEL = 'Cascade test form';
    private const ENTRY_TITLE = 'Une fiche de test';

    private ?string $formId = null;

    /** @var list<string> what this test method created, deleted when it ends */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->getWiki()->services->get(SearchIndexSchema::class)->exists()) {
            $this->markTestSkipped('no search index on this wiki -- run ./yeswicli migrate');
        }
    }

    protected function tearDown(): void
    {
        $wiki = $this->getWiki();
        if ($this->formId !== null) {
            try {
                $wiki->services->get(FormManager::class)->delete($this->formId);
            } catch (\Throwable $ignored) {
            }
            $this->formId = null;
        }

        $pageManager = $wiki->services->get(PageManager::class);
        $indexer = $wiki->services->get(SearchIndexer::class);
        foreach ($this->created as $tag) {
            $pageManager->deleteOrphaned($tag);
            $indexer->delete($tag);
        }
        $this->created = [];

        parent::tearDown();
    }

    /**
     * @return array{0: string, 1: string} the form id and the entry's tag
     */
    private function makeFormWithAnEntry(string $word): array
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);

        $id = 9100;
        while ($formManager->getOne((string)$id) !== null) {
            $id++;
        }
        $this->assertSame(0, $formManager->create([
            'id' => (string)$id,
            'label' => 'Cascade test form',
            'description' => '',
            'template' => [
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'textelong', 'name' => 'bf_description', 'label' => 'Description'],
            ],
            'entry_title_template' => '{{bf_titre}}',
        ]), 'the fixture form should have been created');
        $this->formId = (string)$id;

        $entry = $wiki->services->get(EntryManager::class)->create($this->formId, [
            'form_id' => $this->formId,

            'antispam' => 1,
            'bf_titre' => 'Une fiche de test',
            'bf_description' => $word,
        ]);

        // Both rows are named from what they say -- the form from its label, the entry from its
        // title -- so neither carries a prefix a sweep could match. They are remembered here
        // instead, and tearDown() deletes exactly what this test made.
        $this->created[] = (string)($formManager->getOne($this->formId)['tag'] ?? '');
        $this->created[] = (string)$entry['tag'];

        return [$this->formId, (string)$entry['tag']];
    }

    /**
     * Empty the queue, however much of it belongs to something else.
     *
     * `drain($n)` takes at most $n Contents in queued order, and this suite shares its queue with
     * every other spec that saves anything -- so a single `drain(1000)` can stop short of the row
     * this test just added, which is the newest and therefore the last (ticket 54).
     */
    private function drainEverything(): void
    {
        while ($this->indexer()->drain(1000) > 0) {
        }
    }

    /**
     * Whether draining takes this tag off the queue, waiting for whoever else is draining.
     *
     * Saving a form spawns `search:reindex --drain` in a process of its own
     * (`SearchIndexSubscriber::onFormChanged`), and this test saves a form. Claiming means the two
     * drainers never take the same rows (ticket 54); it does not mean this one can see the queue at
     * an instant of its own choosing -- a row the other process is holding is neither drainable
     * here nor gone yet. So the question is whether the row leaves, not whether it has left by the
     * time this line runs.
     */
    private function leavesTheQueue(string $tag): bool
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $this->drainEverything();
            if (!in_array($tag, $this->queuedTags(), true)) {
                return true;
            }
            usleep(100_000);
        }

        return false;
    }

    private function indexer(): SearchIndexer
    {
        return $this->getWiki()->services->get(SearchIndexer::class);
    }

    public function testAnEntryIsIndexedFromItsFormsFields(): void
    {
        [, $entryTag] = $this->makeFormWithAnEntry('marjolaine');
        $this->indexer()->index($entryTag);

        $found = $this->getWiki()->services->get(SearchIndexQuery::class)->search('marjolaine', null, 10);

        $this->assertSame(1, $found['total']);
        $this->assertSame($entryTag, $found['results'][0]['tag']);
        $this->assertSame('entry', $found['results'][0]['content_type']);
    }

    /**
     * The cascade itself: saving the form must leave its entries queued, whatever happened to the spawn.
     */
    public function testSavingAFormQueuesItsEntries(): void
    {
        [$formId, $entryTag] = $this->makeFormWithAnEntry('sarriette');
        $this->drainEverything();
        $this->assertNotContains($entryTag, $this->queuedTags(), 'this entry starts off the queue');

        $formManager = $this->getWiki()->services->get(FormManager::class);
        $form = $formManager->getOne($formId);
        $form['template'] = [
            ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre renomme'],
            ['type' => 'textelong', 'name' => 'bf_description', 'label' => 'Description renommee'],
        ];
        $formManager->update($form);

        $this->assertContains(
            $entryTag,
            $this->queuedTags(),
            'form.updated must queue the form\'s entries -- the queue, not the spawn, is what carries the work'
        );
    }

    public function testEnqueueFormFindsEveryEntryOfTheForm(): void
    {
        [$formId, $entryTag] = $this->makeFormWithAnEntry('estragon');
        $this->drainEverything();

        $queued = $this->indexer()->enqueueForm($formId);

        $this->assertGreaterThanOrEqual(1, $queued);
        $this->assertContains($entryTag, $this->queuedTags());
    }

    /**
     * Draining reindexes and takes what it did off the queue.
     *
     * Asserted on this test's own tag rather than on `pending()`: that counts the whole queue, and
     * the suite shares its database with whatever else is running -- another spec saving a page, a
     * browser tab on the dev wiki, the shutdown sweep deleting fixtures. A test cannot own a global
     * count, and asserting one is what made this fail once in six runs (ticket 54).
     */
    public function testDrainingTakesWhatItIndexedOffTheQueue(): void
    {
        [, $entryTag] = $this->makeFormWithAnEntry('livreche');
        $this->indexer()->enqueue([$entryTag]);

        $this->assertContains($entryTag, $this->queuedTags());

        $this->assertTrue($this->leavesTheQueue($entryTag), 'draining left this test\'s Content queued');
    }

    /** Two drains never take the same tag: the first claims its chunk, the second gets the next one. */
    public function testTwoDrainsNeverClaimTheSameTag(): void
    {
        $tags = [];
        foreach (['livreche', 'ansérine', 'chénopode'] as $word) {
            [, $tags[]] = $this->makeFormWithAnEntry($word);
        }
        $this->indexer()->enqueue($tags);

        $claimed = [];
        try {
            $claimed = $first = $this->claim(2);
            $second = $this->claim(2);
            $claimed = [...$first, ...$second];

            $this->assertNotEmpty($first);
            $this->assertSame([], array_intersect($first, $second), 'a claimed row was handed out twice');
        } finally {
            $this->release($claimed);
        }
    }

    /**
     * @return list<string> what a drain of this size would take
     */
    private function claim(int $chunkSize): array
    {
        $claim = new \ReflectionMethod($this->indexer(), 'claim');
        $claim->setAccessible(true);

        return $claim->invoke($this->indexer(), $chunkSize, bin2hex(random_bytes(8)));
    }

    /**
     * Hand the rows back: a claim taken and not drained is honoured until it expires, which would
     * leave these tags unindexable for an hour of everybody else's runs.
     *
     * @param list<string> $tags
     */
    private function release(array $tags): void
    {
        if ($tags === []) {
            return;
        }
        $wiki = $this->getWiki();
        $queue = $wiki->services->get(SearchIndexSchema::class)->queueTable();
        $wiki->services->get(\YesWiki\Kernel\Service\DbService::class)->query(
            "UPDATE {$queue} SET claimed_at = NULL, claimed_by = NULL WHERE tag IN ("
            . implode(', ', array_fill(0, count($tags), '?')) . ')',
            $tags
        );
    }

    /** Queueing the same Content twice leaves one row, not two. */
    public function testQueueingIsIdempotent(): void
    {
        [, $entryTag] = $this->makeFormWithAnEntry('cerfeuil');
        $this->drainEverything();

        $this->indexer()->enqueue([$entryTag]);
        $this->indexer()->enqueue([$entryTag]);
        $this->indexer()->enqueue([$entryTag]);

        $this->assertSame([$entryTag], array_values(array_filter(
            $this->queuedTags(),
            static fn (string $tag): bool => $tag === $entryTag
        )));
    }

    /**
     * @return list<string>
     */
    private function queuedTags(): array
    {
        $wiki = $this->getWiki();
        $schema = $wiki->services->get(SearchIndexSchema::class);
        $rows = $wiki->services->get(\YesWiki\Kernel\Service\DbService::class)
            ->loadAll("SELECT tag FROM {$schema->queueTable()}");

        return array_map(static fn (array $row): string => (string)$row['tag'], $rows);
    }

    /**
     * Sweep anything an earlier run left behind, which for a long time was everything.
     *
     * The old sweep looked for `SearchIndexCascadeEntry%` and these fixtures have never been called
     * that, so nothing was ever deleted: a hundred and forty rows accumulated, sharing two titles
     * between them, until they started failing an unrelated test about sorting by title.
     */
    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $dbService = $wiki->services->get(\YesWiki\Kernel\Service\DbService::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $indexer = $wiki->services->get(SearchIndexer::class);

        $rows = $dbService->loadAll(
            'SELECT DISTINCT tag FROM ' . $dbService->prefixTable('pages')
            . ' WHERE tag LIKE ? OR tag LIKE ?',
            [self::tagPrefixOf(self::FORM_LABEL) . '%', self::tagPrefixOf(self::ENTRY_TITLE) . '%']
        );
        foreach ($rows as $row) {
            $pageManager->deleteOrphaned((string)$row['tag']);
            $indexer->delete((string)$row['tag']);
        }

        $dbService->query(
            'DELETE FROM ' . $wiki->services->get(SearchIndexSchema::class)->queueTable()
            . ' WHERE tag LIKE ? OR tag LIKE ?',
            [self::tagPrefixOf(self::FORM_LABEL) . '%', self::tagPrefixOf(self::ENTRY_TITLE) . '%']
        );
    }

    /** The tag a label or a title is turned into: lowercase, words joined by hyphens. */
    private static function tagPrefixOf(string $said): string
    {
        return strtolower(str_replace(' ', '-', $said));
    }
}
