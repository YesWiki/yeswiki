<?php

namespace YesWiki\Test\Bazar\Field;

use YesWiki\Bazar\Field\DateField;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A new date starts with hours or as a whole day as the form says, by default with hours for an event's start and end.
 */
class DateFieldEntryModeTest extends YesWikiTestCase
{
    private $wiki;
    private string $formId;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->formId = $this->wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'Date field entry mode test form',
            'bn_template' => implode("\n", [
                'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'listedatedeb***bf_date_debut_evenement***Début*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***',
                'listedatefin***bf_date_fin_evenement***Fin*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***',
                'listedatedeb***bf_date_echeance***Échéance*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***',
                'listedatedeb***bf_date_rdv***Rendez-vous*** *** *** ***time*** ***0*** *** *** * *** * *** *** *** ***',
                'jour***bf_date_ouverture***Ouverture*** *** *** ***allday*** ***0*** *** *** * *** * *** *** *** ***',
            ]),
            'bn_condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        $this->wiki->services->get(FormManager::class)->delete($this->formId);
    }

    private function field(string $name): DateField
    {
        foreach ($this->wiki->services->get(FormManager::class)->getOne($this->formId)['prepared'] as $field) {
            if ($field instanceof DateField && $field->getPropertyName() === $name) {
                return $field;
            }
        }
        $this->fail("no date field $name");
    }

    private function selected(string $html, string $select): string
    {
        $this->assertMatchesRegularExpression('/<select[^>]*name="[^"]*_' . $select . '"[^>]*>.*?<\/select>/s', $html);
        preg_match('/<select[^>]*name="[^"]*_' . $select . '"[^>]*>(.*?)<\/select>/s', $html, $block);
        preg_match('/<option value="([^"]*)"\s*selected/', $block[1], $option);

        return $option[1] ?? '';
    }

    public function testAnEventStartsAndEndsWithHoursAnHourApart()
    {
        $start = $this->field('bf_date_debut_evenement')->renderInputIfPermitted([]);
        $end = $this->field('bf_date_fin_evenement')->renderInputIfPermitted([]);

        $this->assertSame('0', $this->selected($start, 'allday'));
        $this->assertSame('0', $this->selected($end, 'allday'));
        $startMinutes = (int)$this->selected($start, 'hour') * 60 + (int)$this->selected($start, 'minutes');
        $endMinutes = (int)$this->selected($end, 'hour') * 60 + (int)$this->selected($end, 'minutes');
        $this->assertSame(min(23, (int)date('G') + 1) * 60, $startMinutes);
        $this->assertSame(min($startMinutes + 60, 23 * 60 + 55), $endMinutes);
        $this->assertStringNotContainsString('select-time hide', $start);
    }

    public function testAnotherDateStartsAsAWholeDay()
    {
        $html = $this->field('bf_date_echeance')->renderInputIfPermitted([]);

        $this->assertSame('1', $this->selected($html, 'allday'));
        $this->assertStringContainsString('select-time hide', $html);
    }

    public function testTheFormOptionDecides()
    {
        $this->assertSame('0', $this->selected($this->field('bf_date_rdv')->renderInputIfPermitted([]), 'allday'));
        $this->assertSame('1', $this->selected($this->field('bf_date_ouverture')->renderInputIfPermitted([]), 'allday'));
    }

    public function testAStoredWholeDayStaysAWholeDay()
    {
        $html = $this->field('bf_date_debut_evenement')->renderInputIfPermitted(['bf_date_debut_evenement' => '2026-11-03']);

        $this->assertSame('1', $this->selected($html, 'allday'));
    }

    public function testARefusedSubmissionKeepsTheHoursItWasGiven()
    {
        $html = $this->field('bf_date_echeance')->renderInputIfPermitted([
            'bf_date_echeance' => '2026-11-03',
            'bf_date_echeance_allday' => '0',
            'bf_date_echeance_hour' => '14',
            'bf_date_echeance_minutes' => '30',
        ]);

        $this->assertSame('0', $this->selected($html, 'allday'));
        $this->assertSame('14', $this->selected($html, 'hour'));
        $this->assertSame('30', $this->selected($html, 'minutes'));
        $this->assertStringContainsString('value="2026-11-03"', $html);
    }
}
