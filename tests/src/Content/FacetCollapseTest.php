<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Facets above the list start folded, like the selects of the static lists; beside it the first one stays open. */
class FacetCollapseTest extends YesWikiTestCase
{
    private const FORM_ID = 8651;

    protected function setUp(): void
    {
        $forms = $this->getWiki()->services->get(FormManager::class);
        if ($forms->getOne(self::FORM_ID) !== null) {
            $forms->delete(self::FORM_ID);
        }
        $forms->create([
            'id' => self::FORM_ID,
            'label' => 'FacetCollapseTest',
            'template' => '[{"type": "texte", "name": "bf_titre", "label": "Titre"},'
                . '{"type": "texte", "name": "bf_ville", "label": "Ville"},'
                . '{"type": "texte", "name": "bf_statut", "label": "Statut"}]',
            'condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(FormManager::class)->delete(self::FORM_ID);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<bool>
     */
    private function collapsed(array $options): array
    {
        $services = $this->getWiki()->services;
        $form = $services->get(FormManager::class)->getOne(self::FORM_ID);
        $this->assertNotNull($form);
        $entries = [
            ['tag' => 'Un', 'form_id' => (string)self::FORM_ID, 'bf_ville' => 'Nantes', 'bf_statut' => 'actif'],
            ['tag' => 'Deux', 'form_id' => (string)self::FORM_ID, 'bf_ville' => 'Rennes', 'bf_statut' => 'clos'],
        ];
        $filters = $services->get(BazarListService::class)->getFilters(
            $options + ['groups' => ['bf_ville', 'bf_statut']],
            $entries,
            [self::FORM_ID => $form]
        );

        return array_values(array_map(fn ($filter) => (bool)$filter['collapsed'], $filters));
    }

    public function testFacetsAboveTheListStartFolded(): void
    {
        $this->assertSame([true, true], $this->collapsed(['filterposition' => 'top', 'groupsexpanded' => false]));
    }

    public function testBesideTheListTheFirstFacetStaysOpen(): void
    {
        $this->assertSame([false, true], $this->collapsed(['filterposition' => 'left', 'groupsexpanded' => false]));
    }

    public function testExpandedGroupsWinWhereverTheFacetsSit(): void
    {
        $this->assertSame([false, false], $this->collapsed(['filterposition' => 'top', 'groupsexpanded' => true]));
    }
}
