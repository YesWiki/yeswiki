<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A field name is an SQL identifier: a form refuses anything else, and the search never lets it reach the SQL.
 */
class SearchManagerFieldNameInjectionTest extends YesWikiTestCase
{
    private const PAYLOAD = 'x`,(SELECT SLEEP(5)) as `d';
    private const HYPHEN_FORM_ID = '999911';
    private const MAP_FORM_ID = '999914';

    private function request(array $params): string
    {
        $wiki = $this->getWiki();
        $forms = $wiki->services->get(FormManager::class)->getAll();
        if (empty($forms)) {
            $this->markTestSkipped('needs a form');
        }

        $params += ['formsIds' => [array_key_first($forms)]];

        return $wiki->services->get(SearchManager::class)->prepareSearchRequest($params);
    }

    public function testAQueryOnAnInjectedFieldNameMatchesNothing()
    {
        $this->assertSame('', $this->request(['queries' => self::PAYLOAD . '!=x']));
        $this->assertSame('', $this->request(['queries' => 'bf_titre!=x|' . self::PAYLOAD . '==x']));
    }

    public function testAnInjectedSearchFieldIsIgnored()
    {
        $sql = $this->request(['keywords' => 'robot', 'searchfields' => 'bf_titre,' . self::PAYLOAD]);

        $this->assertNotSame('', $sql);
        $this->assertStringNotContainsString('SLEEP', $sql);
    }

    public function testRealFieldNamesStillReachTheQuery()
    {
        $this->assertStringContainsString('bf_titre', $this->request(['queries' => 'bf_titre!=x']));
        $this->assertStringContainsString('geolocation__bf_latitude', $this->request(['queries' => 'geolocation.bf_latitude!=x']));
    }

    public function testAFieldNameWithAHyphenIsSearchable()
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        $formManager->create([
            'bn_id_nature' => self::HYPHEN_FORM_ID,
            'bn_label_nature' => 'Hyphenated field names',
            'bn_template' => "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\n"
                . "texte***bf_dossier-wiki***Dossier***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***\n"
                . 'checkbox***ListeType***Type*** *** *** ***bf_mes-types*** ***0*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
        $entries = [];
        try {
            $entries[] = $entryManager->create(self::HYPHEN_FORM_ID, ['antispam' => 1, 'bf_titre' => 'Hyphen one', 'bf_dossier-wiki' => 'louise', 'bf_mes-types' => '1,2']);
            $entries[] = $entryManager->create(self::HYPHEN_FORM_ID, ['antispam' => 1, 'bf_titre' => 'Hyphen two', 'bf_dossier-wiki' => 'marcel', 'bf_mes-types' => '3']);
            $search = fn (string $query) => array_column($entryManager->search([
                'formsIds' => [self::HYPHEN_FORM_ID],
                'queries' => $wiki->services->get(SearchManager::class)->parseQuery($query),
            ]), 'bf_titre');

            $this->assertSame(['Hyphen one'], $search('bf_dossier-wiki=louise'));
            $this->assertSame(['Hyphen one'], $search('bf_mes-types=2'));
            $this->assertSame(['Hyphen two'], $search('bf_mes-types=3'));
        } finally {
            foreach ($entries as $entry) {
                $entryManager->delete($entry['id_fiche'], true);
            }
            $formManager->delete(self::HYPHEN_FORM_ID);
        }
    }

    public function testADeepPathWithANonExistentTailIsTreatedAsMissing()
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        $formManager->create([
            'bn_id_nature' => self::MAP_FORM_ID,
            'bn_label_nature' => 'Structured field',
            'bn_template' => "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\n"
                . 'map***bf_latitude***bf_longitude*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
        $entries = [];
        try {
            $entries[] = $entryManager->create(self::MAP_FORM_ID, ['antispam' => 1, 'bf_titre' => 'A placed entry', 'bf_latitude' => '1.5', 'bf_longitude' => '2.5']);
            $search = fn (string $query) => array_column($entryManager->search([
                'formsIds' => [self::MAP_FORM_ID],
                'queries' => $wiki->services->get(SearchManager::class)->parseQuery($query),
            ]), 'bf_titre');

            $this->assertSame([], $search('bf_latitude.nope==x'), 'a non-existent sub-field matches nothing');
            $this->assertSame(['A placed entry'], $search('bf_latitude.nope!=x'), 'and its negation matches everything');
        } finally {
            foreach ($entries as $entry) {
                $entryManager->delete($entry['id_fiche'], true);
            }
            $formManager->delete(self::MAP_FORM_ID);
        }
    }

    public function testAFormReportsItsInvalidFieldNames()
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $form = $formManager->getFromRawData([
            'bn_id_nature' => '999913',
            'bn_label_nature' => 'Field name rule',
            'bn_template' => "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\n"
                . "texte***bf_dossier-wiki***Dossier***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***\n"
                . "texte***bf_x`y***Robot***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***\n"
                . 'texte***bf_{z}***Accolades***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);

        $this->assertSame(['bf_x`y', 'bf_{z}'], $formManager->invalidPropertyNames($form));
    }
}
