<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A facet over a hierarchical list nests its values, and a checked value keeps the entries filed under its descendants. */
class EntryListFacetTreeTest extends YesWikiTestCase
{
    private const FORM_ID = '999941';
    private const LIST_ID = 'ListeEntryListFacetTreeTest';
    private const ENTRIES = [
        'FacetTreeTestLegumes' => ['Des légumes', 'legumes'],
        'FacetTreeTestTomates' => ['Des tomates', 'tomate'],
        'FacetTreeTestPommes' => ['Des pommes', 'pomme'],
    ];

    private ?Request $requestWas = null;

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        $this->requestWas = $services->get(CurrentRequest::class)->get();

        $services->get(ListManager::class)->create('Produits', [
            ['id' => 'legumes', 'label' => 'Légumes', 'children' => [
                ['id' => 'tomate', 'label' => 'Tomate', 'children' => []],
                ['id' => 'courge', 'label' => 'Courge', 'children' => []],
            ]],
            ['id' => 'fruits', 'label' => 'Fruits', 'children' => [
                ['id' => 'pomme', 'label' => 'Pomme', 'children' => []],
            ]],
        ], self::LIST_ID);
        $services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Formulaire de test des facettes en arbre',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'checkbox', 'linked_object' => self::LIST_ID, 'name' => 'bf_produit', 'label' => 'Produit'],
            ]),
            'condition' => '',
        ]);
        foreach (self::ENTRIES as $tag => [$title, $value]) {
            $services->get(EntryManager::class)->create(self::FORM_ID, [
                'antispam' => 1,
                'tag' => $tag,
                'bf_titre' => $title,
                'checkboxListeEntryListFacetTreeTestbf_produit' => $value,
                'bf_produit' => $value,
            ]);
        }
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        if ($this->requestWas !== null) {
            $services->get(CurrentRequest::class)->replace($this->requestWas);
        }
        $entryManager = $services->get(EntryManager::class);
        foreach (array_keys(self::ENTRIES) as $tag) {
            if ($entryManager->isEntry($tag)) {
                $entryManager->delete($tag, true);
            }
        }
        $services->get(FormManager::class)->delete(self::FORM_ID);
        $services->get(PageManager::class)->deleteOrphaned(self::LIST_ID);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $extra
     */
    private function render(array $query = [], array $extra = []): string
    {
        $services = $this->getWiki()->services;
        $services->get(CurrentRequest::class)->replace(Request::create('/', 'GET', $query));

        return (string)$services->get(Performer::class)->run('entrylist', 'action', $extra + [
            'id' => self::FORM_ID,
            'template' => 'card',
            'groups' => 'bf_produit',
        ]);
    }

    /** @return list<string> */
    private function titles(string $html): array
    {
        preg_match_all('#class="yw-item__title"[^>]*>(?:<[^>]+>\s*)*([^<]+)<#', $html, $matches);

        return array_map('trim', $matches[1]);
    }

    private function facetCount(string $html, string $value): ?int
    {
        if (preg_match('#value="' . $value . '"[^>]*>\s*<span>[^<]*<span class="nb-wrapper">\(<span class="nb">(\d+)<#', $html, $match) !== 1) {
            return null;
        }

        return (int)$match[1];
    }

    public function testTheValuesNestUnderTheirParentAndAParentCountsItsDescendants(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('#value="legumes".*?<details class="facet-node__children">.*?value="tomate"#s', $html, 'a child is drawn inside its parent');
        $this->assertSame(2, $this->facetCount($html, 'legumes'), 'the parent counts the entry filed under it and the one filed under a child');
        $this->assertSame(1, $this->facetCount($html, 'tomate'));
        $this->assertNull($this->facetCount($html, 'courge'), 'a value no entry holds is not offered');
    }

    public function testCheckingAParentKeepsTheEntriesOfItsDescendants(): void
    {
        $titles = $this->titles($this->render(['facet' => ['bf_produit' => ['legumes']]]));
        sort($titles);

        $this->assertSame(['Des légumes', 'Des tomates'], $titles);
    }

    public function testACheckedChildOpensItsParent(): void
    {
        $html = $this->render(['facet' => ['bf_produit' => ['tomate']]]);

        $this->assertSame(['Des tomates'], $this->titles($html));
        $this->assertMatchesRegularExpression('#<details class="facet-node__children" open>.*?value="tomate"\s+checked#s', $html);
    }

    public function testFacetsAboveTheListIndentTheChildren(): void
    {
        $html = $this->render([], ['filterposition' => 'top']);

        $this->assertStringContainsString(htmlspecialchars('"tomate":"\u2014 Tomate (1)"'), $html);
    }
}
