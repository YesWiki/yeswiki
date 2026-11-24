<?php

namespace YesWiki\Test\Content;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Action\EntryListAction;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Render\Service\Performer;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The calendar gets one event per dated entry, recurring ones left for the browser to unfold. */
class EntryCalendarEventsTest extends YesWikiTestCase
{
    private ?Request $requestWas = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestWas = $this->getWiki()->services->get(CurrentRequest::class)->get();
    }

    protected function tearDown(): void
    {
        if ($this->requestWas !== null) {
            $this->getWiki()->services->get(CurrentRequest::class)->replace($this->requestWas);
        }
        parent::tearDown();
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param array<string, string>      $arguments
     * @param array<string, mixed>       $query
     *
     * @return array<string, mixed>
     */
    private function calendarFor(array $entries, array $arguments = [], array $query = []): array
    {
        $wiki = $this->getWiki();
        $wiki->services->get(CurrentRequest::class)->replace(Request::create('/', 'GET', $query));
        $action = new EntryListAction();
        $action->setServices($wiki->services);
        $action->setParams($wiki->services->get(ParameterBagInterface::class));
        $action->setTwig($wiki->services->get(TemplateEngine::class));
        $arguments += ['template' => 'calendar', 'id' => '2'];
        $action->setArguments($arguments);

        return (new \ReflectionMethod(EntryListAction::class, 'calendarSetup'))->invoke($action, $entries);
    }

    /**
     * @param array<string, mixed> $more
     *
     * @return array<string, mixed>
     */
    private static function event(string $tag, array $more = []): array
    {
        return $more + ['tag' => $tag, 'form_id' => '2', 'bf_titre' => $tag, 'bf_date_debut_evenement' => '2026-11-24', 'bf_date_fin_evenement' => '2026-11-25'];
    }

    public function testEachDatedEntryIsAnEvent(): void
    {
        $calendar = $this->calendarFor([
            self::event('Atelier'),
            self::event('SansDate', ['bf_date_debut_evenement' => '']),
            self::event('Enfant', ['bf_date_fin_evenement_data' => '{"recurrentParentId":"Atelier"}']),
        ]);

        $this->assertSame(['Atelier'], array_column($calendar['events'], 'tag'));
        $this->assertSame('2026-11-24', $calendar['events'][0]['start']);
        $this->assertSame('2026-11-25', $calendar['events'][0]['end']);
        $this->assertStringEndsWith('Atelier', $calendar['events'][0]['url']);
    }

    public function testARecurringEntryCarriesItsRule(): void
    {
        $rule = ['isRecurrent' => '1', 'repetition' => 'w', 'step' => '1'];
        $calendar = $this->calendarFor([
            self::event('Hebdo', ['bf_date_fin_evenement_data' => $rule]),
            self::event('Unique', ['bf_date_fin_evenement_data' => ['isRecurrent' => '0']]),
        ]);

        $this->assertSame($rule, $calendar['events'][0]['recurrence']);
        $this->assertArrayNotHasKey('recurrence', $calendar['events'][1]);
    }

    public function testTheViewsFollowTheSettings(): void
    {
        $calendar = $this->calendarFor([], ['showlist' => 'month', 'initialview' => 'list', 'entrydisplay' => 'popup']);

        $this->assertSame('listMonth', $calendar['listView']);
        $this->assertSame('listMonth', $calendar['initialView']);
        $this->assertSame('modal', $calendar['display'], 'a way of opening the calendar does not know opens a modal');
        $this->assertNull($calendar['icalUrl']);
    }

    public function testTheFeedNamesTheEntriesOnceAFacetNarrowsThem(): void
    {
        $whole = $this->calendarFor([self::event('Atelier')], ['showicalbutton' => 'true']);
        $narrowed = $this->calendarFor([self::event('Atelier')], ['showicalbutton' => 'true'], ['facet' => ['bf_type' => ['x']]]);

        $this->assertStringContainsString('api/entries/ical', (string)$whole['icalUrl']);
        $this->assertStringContainsString('id=2', (string)$whole['icalUrl']);
        $this->assertStringNotContainsString('query=', (string)$whole['icalUrl']);
        $this->assertStringContainsString('query=' . urlencode('tag=Atelier'), (string)$narrowed['icalUrl']);
    }

    public function testTheCalendarPageIsDrawnOnTheServer(): void
    {
        $html = (string)$this->getWiki()->services->get(Performer::class)->run('calendar', 'action', ['id' => '2']);

        $this->assertStringContainsString('class="yw-entry-calendar__data"', $html);
        $this->assertStringNotContainsString('<Bazar-Calendar', $html);
    }
}
