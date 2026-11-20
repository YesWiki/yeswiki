<?php
// The id_typeannonce facet lists the forms of the entries, whatever facet comes before it.

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class BazarListFormFilterTest extends YesWikiTestCase
{
    private $wiki;
    private string $listId;
    private array $formIds = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->listId = $this->wiki->services->get(ListManager::class)->create('Form filter test years', [
            ['id' => '2025', 'label' => '2025'],
            ['id' => '2026', 'label' => '2026'],
        ], 'FormFilterYears' . bin2hex(random_bytes(4)));
        foreach (['Adhésions personnes', 'Adhésions groupes'] as $label) {
            $this->formIds[] = $this->wiki->services->get(FormManager::class)->create([
                'bn_label_nature' => $label,
                'bn_template' => implode("\n", [
                    'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
                    'liste***' . $this->listId . '***Année*** *** *** ***bf_annee*** ***0*** *** *** * *** * *** *** *** ***',
                ]),
                'bn_condition' => '',
            ]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->formIds as $formId) {
            $this->wiki->services->get(FormManager::class)->delete($formId);
        }
        $this->wiki->services->get(PageManager::class)->deleteOrphaned($this->listId);
        $this->wiki->services->get(TripleStore::class)->delete($this->listId, TripleStore::TYPE_URI, null, '', '');
    }

    public function testTheFormFacetAfterAListFacetListsTheForms()
    {
        $formManager = $this->wiki->services->get(FormManager::class);
        $forms = [];
        $entries = [];
        foreach ($this->formIds as $formId) {
            $forms[$formId] = $formManager->getOne($formId);
            $entries[] = ['id_fiche' => 'Fiche' . $formId, 'id_typeannonce' => $formId, 'bf_annee' => '2026'];
        }

        $filters = $this->wiki->services->get(BazarListService::class)->getFilters(['groups' => ['bf_annee', 'id_typeannonce']], $entries, $forms);
        $formFilter = array_values(array_filter($filters, fn ($f) => $f['propName'] === 'id_typeannonce'))[0];

        $values = array_column($formFilter['nodes'], 'value');
        sort($values);
        $expected = array_map('strval', $this->formIds);
        sort($expected);
        $this->assertSame($expected, array_map('strval', $values));
    }
}
