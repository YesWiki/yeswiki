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
    private const ENTRY_TAG_PREFIX = 'SearchIndexCascadeEntry';

    private ?string $formId = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->getWiki()->services->get(SearchIndexSchema::class)->exists()) {
            $this->markTestSkipped('no search index on this wiki -- run ./yeswicli migrate');
        }
    }

    protected function tearDown(): void
    {
        if ($this->formId !== null) {
            try {
                $this->getWiki()->services->get(FormManager::class)->delete($this->formId);
            } catch (\Throwable $ignored) {
            }
            $this->formId = null;
        }
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

        return [$this->formId, (string)$entry['tag']];
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
        $this->indexer()->drain(1000);
        $this->assertSame(0, $this->indexer()->pending(), 'the queue starts empty');

        $formManager = $this->getWiki()->services->get(FormManager::class);
        $form = $formManager->getOne($formId);
        $form['template'] = [
            ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre renomme'],
            ['type' => 'textelong', 'name' => 'bf_description', 'label' => 'Description renommee'],
        ];
        $formManager->update($form);

        $this->assertGreaterThan(
            0,
            $this->indexer()->pending(),
            'form.updated must queue the form\'s entries -- the queue, not the spawn, is what carries the work'
        );
        $this->assertContains($entryTag, $this->queuedTags());
    }

    public function testEnqueueFormFindsEveryEntryOfTheForm(): void
    {
        [$formId, $entryTag] = $this->makeFormWithAnEntry('estragon');
        $this->indexer()->drain(1000);

        $queued = $this->indexer()->enqueueForm($formId);

        $this->assertGreaterThanOrEqual(1, $queued);
        $this->assertContains($entryTag, $this->queuedTags());
    }

    /** Draining reindexes and empties, and is safe to run when there is nothing to do. */
    public function testDrainingEmptiesTheQueueAndIsIdempotent(): void
    {
        [, $entryTag] = $this->makeFormWithAnEntry('livreche');
        $this->indexer()->enqueue([$entryTag]);

        $this->assertGreaterThan(0, $this->indexer()->pending());
        $this->indexer()->drain(1000);
        $this->assertSame(0, $this->indexer()->pending());

        $this->assertSame(0, $this->indexer()->drain(1000), 'draining an empty queue does nothing');
    }

    /** Queueing the same Content twice leaves one row, not two. */
    public function testQueueingIsIdempotent(): void
    {
        [, $entryTag] = $this->makeFormWithAnEntry('cerfeuil');
        $this->indexer()->drain(1000);

        $this->indexer()->enqueue([$entryTag]);
        $this->indexer()->enqueue([$entryTag]);
        $this->indexer()->enqueue([$entryTag]);

        $this->assertSame(1, $this->indexer()->pending());
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

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $indexer = $wiki->services->get(SearchIndexer::class);
        foreach ($wiki->services->get(\YesWiki\Kernel\Service\DbService::class)->loadAll(
            'SELECT DISTINCT tag FROM ' . $wiki->services->get(\YesWiki\Kernel\Service\DbService::class)->prefixTable('pages')
            . " WHERE tag LIKE '" . self::ENTRY_TAG_PREFIX . "%'"
        ) as $row) {
            $pageManager->deleteOrphaned((string)$row['tag']);
            $indexer->delete((string)$row['tag']);
        }
    }
}
