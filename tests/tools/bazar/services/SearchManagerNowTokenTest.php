<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The [now] and [today] tokens, with offsets like [today+3w], stand for a date relative to the current one.
 */
class SearchManagerNowTokenTest extends YesWikiTestCase
{
    public function testNowBecomesTheCurrentDateTime()
    {
        $before = date('c');
        $query = $this->getWiki()->services->get(SearchManager::class)->parseQuery('bf_date_debut_evenement>=[now]');
        $after = date('c');

        $value = array_values($query)[0]['values'][0];
        $this->assertGreaterThanOrEqual($before, $value);
        $this->assertLessThanOrEqual($after, $value);
    }

    public function testTodayTakesOffsets()
    {
        $value = fn (string $token) => array_values($this->getWiki()->services->get(SearchManager::class)->parseQuery("bf_date>=$token"))[0]['values'][0];
        $today = new \DateTimeImmutable('today');

        $this->assertSame($today->format('Y-m-d'), $value('[today]'));
        $this->assertSame($today->modify('+3 week')->format('Y-m-d'), $value('[today+3w]'));
        $this->assertSame($today->modify('+3 week')->format('Y-m-d'), $value('[today+3s]'));
        $this->assertSame($today->modify('-2 month')->format('Y-m-d'), $value('[today-2m]'));
        $this->assertSame($today->modify('+1 year')->modify('-1 day')->format('Y-m-d'), $value('[today+1a-1j]'));
        $this->assertSame('[today+3x]', $value('[today+3x]'), 'an unknown unit is left as it is');
        $this->assertSame('[tomorrow]', $value('[tomorrow]'));
    }

    public function testTodayKeepsTheEventsOfTheDay()
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        $formId = $formManager->create([
            'bn_label_nature' => 'Events of the day',
            'bn_template' => "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\n"
                . 'jour***bf_date_debut_evenement***Début*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
        $entries = [];
        try {
            $entries[] = $entryManager->create($formId, ['antispam' => 1, 'bf_titre' => 'Yesterday', 'bf_date_debut_evenement' => date('Y-m-d', strtotime('-1 day'))]);
            $entries[] = $entryManager->create($formId, ['antispam' => 1, 'bf_titre' => 'All day today', 'bf_date_debut_evenement' => date('Y-m-d')]);
            $entries[] = $entryManager->create($formId, ['antispam' => 1, 'bf_titre' => 'In a month', 'bf_date_debut_evenement' => date('Y-m-d', strtotime('+1 month'))]);
            $search = fn (string $query) => array_column($entryManager->search([
                'formsIds' => [$formId],
                'queries' => $wiki->services->get(SearchManager::class)->parseQuery($query),
            ]), 'bf_titre');

            $upcoming = $search('bf_date_debut_evenement>=[today]');
            sort($upcoming);
            $this->assertSame(['All day today', 'In a month'], $upcoming);
            $this->assertSame(['All day today'], $search('bf_date_debut_evenement>=[today]|bf_date_debut_evenement<[today+1w]'));
            $this->assertSame(['All day today'], $search('bf_date_debut_evenement>=[today] AND bf_date_debut_evenement<[today+1s]'));
        } finally {
            foreach ($entries as $entry) {
                $entryManager->delete($entry['id_fiche'], true);
            }
            $formManager->delete($formId);
        }
    }

    public function testNowKeepsOnlyUpcomingEntries()
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        $formId = $formManager->create([
            'bn_label_nature' => 'Events with a start date',
            'bn_template' => "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\n"
                . 'jour***bf_date_debut_evenement***Début*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
        $entries = [];
        try {
            $entries[] = $entryManager->create($formId, ['antispam' => 1, 'bf_titre' => 'Past', 'bf_date_debut_evenement' => date('Y-m-d', strtotime('-2 days'))]);
            $entries[] = $entryManager->create($formId, ['antispam' => 1, 'bf_titre' => 'Earlier today', 'bf_date_debut_evenement' => date('c', strtotime('-1 hour'))]);
            $entries[] = $entryManager->create($formId, ['antispam' => 1, 'bf_titre' => 'Later today', 'bf_date_debut_evenement' => date('c', strtotime('+1 hour'))]);
            $entries[] = $entryManager->create($formId, ['antispam' => 1, 'bf_titre' => 'Upcoming', 'bf_date_debut_evenement' => date('Y-m-d', strtotime('+2 days'))]);
            $search = fn (string $query) => array_column($entryManager->search([
                'formsIds' => [$formId],
                'queries' => $wiki->services->get(SearchManager::class)->parseQuery($query),
            ]), 'bf_titre');

            $upcoming = $search('bf_date_debut_evenement>=[now]');
            sort($upcoming);
            $this->assertSame(['Later today', 'Upcoming'], $upcoming);
            $past = $search('bf_date_debut_evenement<[now]');
            sort($past);
            $this->assertSame(['Earlier today', 'Past'], $past);
        } finally {
            foreach ($entries as $entry) {
                $entryManager->delete($entry['id_fiche'], true);
            }
            $formManager->delete($formId);
        }
    }

    public function testAnEntryListFieldOffersOnlyUpcomingEntries()
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        $eventsId = $formManager->create([
            'bn_label_nature' => 'Workshops',
            'bn_template' => "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\n"
                . 'jour***bf_date_debut_evenement***Début*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
        $selectField = array_fill(0, 16, ' ');
        [$selectField[0], $selectField[1], $selectField[2], $selectField[15]] = ['listefiche', $eventsId, 'Atelier', 'bf_date_debut_evenement>=[now]'];
        $signupId = $formManager->create([
            'bn_label_nature' => 'Sign-up',
            'bn_template' => "texte***bf_titre***Nom***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\n"
                . implode('***', $selectField),
            'bn_condition' => '',
        ]);
        $entries = [];
        try {
            $entries[] = $entryManager->create($eventsId, ['antispam' => 1, 'bf_titre' => 'Past workshop', 'bf_date_debut_evenement' => date('Y-m-d', strtotime('-2 days'))]);
            $entries[] = $entryManager->create($eventsId, ['antispam' => 1, 'bf_titre' => 'Next workshop', 'bf_date_debut_evenement' => date('Y-m-d', strtotime('+2 days'))]);

            $fields = array_filter($formManager->getOne($signupId)['prepared'], fn ($field) => $field instanceof EnumField);
            $this->assertCount(1, $fields);
            $this->assertSame(['Next workshop'], array_values(array_shift($fields)->getOptions()));
        } finally {
            foreach ($entries as $entry) {
                $entryManager->delete($entry['id_fiche'], true);
            }
            $formManager->delete($signupId);
            $formManager->delete($eventsId);
        }
    }
}
