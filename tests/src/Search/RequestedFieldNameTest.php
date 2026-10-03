<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A field name is an identifier: a form refuses anything else, a search request never lets it reach the SQL, and a hyphen is fine. */
class RequestedFieldNameTest extends YesWikiTestCase
{
    private const FORM_ID = 8633;
    private const PAYLOAD = 'x`,(SELECT 1) as `d';
    private const MAP_FORM_ID = 8634;

    protected function setUp(): void
    {
        $forms = $this->getWiki()->services->get(FormManager::class);
        if ($forms->getOne(self::FORM_ID) !== null) {
            $forms->delete(self::FORM_ID);
        }
        $forms->create([
            'id' => self::FORM_ID,
            'label' => 'RequestedFieldNameTest',
            'template' => '[{"type": "texte", "name": "bf_titre", "label": "Titre"},'
                . '{"type": "texte", "name": "bf_dossier-wiki", "label": "Dossier"}]',
            'condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(FormManager::class)->delete(self::FORM_ID);
    }

    /** @param array<string, mixed> $params */
    private function request(array $params): string
    {
        $params += ['formsIds' => [self::FORM_ID]];

        return $this->getWiki()->services->get(SearchManager::class)->prepareSearchRequest($params)->sql;
    }

    public function testAQueryOnAnInvalidFieldNameMatchesNothing(): void
    {
        $this->assertSame('', $this->request(['queries' => self::PAYLOAD . '!=x']));
        $this->assertSame('', $this->request(['queries' => 'bf_titre!=x|' . self::PAYLOAD . '==x']));
    }

    public function testAnInvalidSearchFieldIsIgnored(): void
    {
        $sql = $this->request(['keywords' => 'robot', 'searchfields' => 'bf_titre,' . self::PAYLOAD]);

        $this->assertNotSame('', $sql);
        $this->assertStringNotContainsString('SELECT 1', $sql);
    }

    public function testAFieldNameWithAHyphenIsSearchable(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $entryManager = $wiki->services->get(EntryManager::class);
        $tags = [];
        try {
            $tags[] = $entryManager->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => 'Hyphen one', 'bf_dossier-wiki' => 'louise'])['tag'];
            $tags[] = $entryManager->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => 'Hyphen two', 'bf_dossier-wiki' => 'marcel'])['tag'];

            $found = $entryManager->search([
                'formsIds' => [self::FORM_ID],
                'queries' => $wiki->services->get(SearchManager::class)->parseQuery('bf_dossier-wiki=louise'),
            ]);

            $this->assertSame(['Hyphen one'], array_column($found, 'bf_titre'));
        } finally {
            foreach ($tags as $tag) {
                $entryManager->delete($tag, true);
            }
        }
    }

    public function testADeepPathWithANonExistentTailIsTreatedAsMissing(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $forms = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        if ($forms->getOne(self::MAP_FORM_ID) !== null) {
            $forms->delete(self::MAP_FORM_ID);
        }
        $forms->create([
            'id' => self::MAP_FORM_ID,
            'label' => 'RequestedFieldNameTest map',
            'template' => '[{"type": "texte", "name": "bf_titre", "label": "Titre"},'
                . '{"type": "map", "name": "bf_latitude", "label": "bf_longitude"}]',
            'condition' => '',
        ]);
        $tags = [];
        try {
            $tags[] = $entryManager->create(self::MAP_FORM_ID, ['antispam' => 1, 'bf_titre' => 'A placed entry', 'bf_latitude' => '1.5', 'bf_longitude' => '2.5'])['tag'];
            $search = fn (string $query) => array_column($entryManager->search([
                'formsIds' => [self::MAP_FORM_ID],
                'queries' => $wiki->services->get(SearchManager::class)->parseQuery($query),
            ]), 'bf_titre');

            $this->assertSame([], $search('bf_latitude.nope==x'), 'a non-existent sub-field matches nothing');
            $this->assertSame(['A placed entry'], $search('bf_latitude.nope!=x'), 'and its negation matches everything');
        } finally {
            foreach ($tags as $tag) {
                $entryManager->delete($tag, true);
            }
            $forms->delete(self::MAP_FORM_ID);
        }
    }

    public function testAFormReportsItsInvalidFieldNames(): void
    {
        $forms = $this->getWiki()->services->get(FormManager::class);
        $form = $forms->getFromRawData([
            'id' => self::FORM_ID,
            'label' => 'RequestedFieldNameTest',
            'template' => '[{"type": "texte", "name": "bf_titre", "label": "Titre"},'
                . '{"type": "texte", "name": "bf_dossier-wiki", "label": "Dossier"},'
                . '{"type": "texte", "name": "bf_x`y", "label": "Robot"},'
                . '{"type": "texte", "name": "bf_{z}", "label": "Accolades"}]',
            'condition' => '',
        ]);

        $this->assertSame(['bf_x`y', 'bf_{z}'], $forms->invalidPropertyNames($form));
    }
}
