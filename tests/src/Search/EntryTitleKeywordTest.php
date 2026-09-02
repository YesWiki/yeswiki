<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A keyword search over a form matches its entries' titles even when no field is called `bf_titre`. */
class EntryTitleKeywordTest extends YesWikiTestCase
{
    private ?string $formId = null;

    /** @var list<string> */
    private array $entries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $id = 9760;
        while ($formManager->getOne((string)$id) !== null) {
            $id++;
        }
        $this->assertSame(0, $formManager->create([
            'id' => (string)$id,
            'label' => 'EntryTitleKeywordTest',
            'entry_title_template' => '{{bf_nom}}',
            'template' => [
                ['type' => 'texte', 'name' => 'bf_nom', 'label' => 'Nom'],
            ],
        ]));
        $this->formId = (string)$id;

        $entryManager = $this->getWiki()->services->get(EntryManager::class);
        foreach (['Ada Lovelace', 'Elizabeth Feinler'] as $name) {
            $entry = $entryManager->create($this->formId, ['antispam' => 1, 'bf_nom' => $name]);
            $this->entries[] = (string)$entry['tag'];
        }
    }

    protected function tearDown(): void
    {
        $entryManager = $this->getWiki()->services->get(EntryManager::class);
        foreach ($this->entries as $tag) {
            $entryManager->delete($tag, true);
        }
        if ($this->formId !== null) {
            $this->getWiki()->services->get(FormManager::class)->delete($this->formId);
        }
        parent::tearDown();
    }

    public function testAKeywordNarrowsTheListToTheEntriesWhoseTitleCarriesIt(): void
    {
        $search = $this->getWiki()->services->get(SearchManager::class);

        $this->assertCount(2, $search->search(['formsIds' => [$this->formId]], true, true), 'the premise: both entries are listed');
        $this->assertCount(1, $search->search(['formsIds' => [$this->formId], 'keywords' => 'Lovelace'], true, true), 'the keyword did not narrow the list to the matching title');
        $this->assertCount(0, $search->search(['formsIds' => [$this->formId], 'keywords' => 'nobodyisnamedthis'], true, true), 'a keyword nothing matches still lists everything');
    }
}
