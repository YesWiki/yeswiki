<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The [now] and [today] tokens, with offsets like [today+3w], stand for a date relative to the current one. */
class DateTokenTest extends YesWikiTestCase
{
    private const EVENTS_FORM_ID = 8641;
    private const SIGNUP_FORM_ID = 8642;

    protected function setUp(): void
    {
        $forms = $this->getWiki()->services->get(FormManager::class);
        foreach ([self::SIGNUP_FORM_ID, self::EVENTS_FORM_ID] as $id) {
            if ($forms->getOne($id) !== null) {
                $forms->delete($id);
            }
        }
        $forms->create([
            'id' => self::EVENTS_FORM_ID,
            'label' => 'DateTokenTest events',
            'template' => '[{"type": "texte", "name": "bf_titre", "label": "Titre"},'
                . '{"type": "jour", "name": "bf_date_debut_evenement", "label": "Début"}]',
            'condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        $forms = $this->getWiki()->services->get(FormManager::class);
        foreach ([self::SIGNUP_FORM_ID, self::EVENTS_FORM_ID] as $id) {
            if ($forms->getOne($id) !== null) {
                $forms->delete($id);
            }
        }
    }

    private function value(string $token): mixed
    {
        $query = $this->getWiki()->services->get(SearchManager::class)->parseQuery("bf_date>=$token");

        return array_values($query)[0]['values'][0];
    }

    /** @param array<string, string> $startDates title => start date */
    private function withEvents(array $startDates, callable $work): mixed
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $entryManager = $wiki->services->get(EntryManager::class);
        $tags = [];
        try {
            foreach ($startDates as $title => $date) {
                $tags[] = $entryManager->create(self::EVENTS_FORM_ID, ['antispam' => 1, 'bf_titre' => $title, 'bf_date_debut_evenement' => $date])['tag'];
            }

            return $work(function (string $query) use ($entryManager, $wiki): array {
                $titles = array_column($entryManager->search([
                    'formsIds' => [self::EVENTS_FORM_ID],
                    'queries' => $wiki->services->get(SearchManager::class)->parseQuery($query),
                ]), 'bf_titre');
                sort($titles);

                return $titles;
            });
        } finally {
            foreach ($tags as $tag) {
                $entryManager->delete($tag, true);
            }
        }
    }

    public function testNowBecomesTheCurrentDateTime(): void
    {
        $before = date('c');
        $value = $this->value('[now]');
        $after = date('c');

        $this->assertGreaterThanOrEqual($before, $value);
        $this->assertLessThanOrEqual($after, $value);
    }

    public function testTodayTakesOffsets(): void
    {
        $today = new \DateTimeImmutable('today');

        $this->assertSame($today->format('Y-m-d'), $this->value('[today]'));
        $this->assertSame($today->modify('+3 week')->format('Y-m-d'), $this->value('[today+3w]'));
        $this->assertSame($today->modify('+3 week')->format('Y-m-d'), $this->value('[today+3s]'));
        $this->assertSame($today->modify('-2 month')->format('Y-m-d'), $this->value('[today-2m]'));
        $this->assertSame($today->modify('+1 year')->modify('-1 day')->format('Y-m-d'), $this->value('[today+1a-1j]'));
        $this->assertSame('[today+3x]', $this->value('[today+3x]'), 'an unknown unit is left as it is');
        $this->assertSame('[tomorrow]', $this->value('[tomorrow]'));
    }

    public function testTheExpressionSyntaxResolvesTokensToo(): void
    {
        $tree = (string)json_encode($this->getWiki()->services->get(SearchManager::class)->parseQueryExpression('bf_date>=[today] AND bf_date<[today+1w]'));

        $this->assertStringContainsString((new \DateTimeImmutable('today'))->format('Y-m-d'), $tree);
        $this->assertStringContainsString((new \DateTimeImmutable('today +1 week'))->format('Y-m-d'), $tree);
        $this->assertStringNotContainsString('[today', $tree);
    }

    public function testTodayKeepsTheEventsOfTheDay(): void
    {
        $this->withEvents([
            'Yesterday' => date('Y-m-d', strtotime('-1 day')),
            'All day today' => date('Y-m-d'),
            'In a month' => date('Y-m-d', strtotime('+1 month')),
        ], function (callable $search): void {
            $this->assertSame(['All day today', 'In a month'], $search('bf_date_debut_evenement>=[today]'));
            $this->assertSame(['All day today'], $search('bf_date_debut_evenement>=[today]|bf_date_debut_evenement<[today+1w]'));
            $this->assertSame(['All day today'], $search('bf_date_debut_evenement>=[today] AND bf_date_debut_evenement<[today+1s]'));
        });
    }

    public function testNowKeepsOnlyUpcomingEntries(): void
    {
        $this->withEvents([
            'Past' => date('Y-m-d', strtotime('-2 days')),
            'Earlier today' => date('c', strtotime('-1 hour')),
            'Later today' => date('c', strtotime('+1 hour')),
            'Upcoming' => date('Y-m-d', strtotime('+2 days')),
        ], function (callable $search): void {
            $this->assertSame(['Later today', 'Upcoming'], $search('bf_date_debut_evenement>=[now]'));
            $this->assertSame(['Earlier today', 'Past'], $search('bf_date_debut_evenement<[now]'));
        });
    }

    public function testAnEntryListFieldOffersOnlyUpcomingEntries(): void
    {
        $forms = $this->getWiki()->services->get(FormManager::class);
        $forms->create([
            'id' => self::SIGNUP_FORM_ID,
            'label' => 'DateTokenTest sign-up',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Nom'],
                ['type' => 'listefiche', 'linked_object' => (string)self::EVENTS_FORM_ID, 'label' => 'Atelier', 'queries' => 'bf_date_debut_evenement>=[now]'],
            ]),
            'condition' => '',
        ]);

        $this->withEvents([
            'Past workshop' => date('Y-m-d', strtotime('-2 days')),
            'Next workshop' => date('Y-m-d', strtotime('+2 days')),
        ], function () use ($forms): void {
            $fields = array_values(array_filter($forms->getOne(self::SIGNUP_FORM_ID)['prepared'] ?? [], fn ($field) => $field instanceof EnumField));
            $this->assertCount(1, $fields);
            $this->assertSame(['Next workshop'], array_values($fields[0]->getOptions()));
        });
    }
}
