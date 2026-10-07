<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Field\DateField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Default entry mode of date fields. */
class DateFieldEntryModeTest extends YesWikiTestCase
{
    private function field(string $type, string $name, string $entryMode = ''): DateField
    {
        $definition = ['type' => $type, 'name' => $name, 'label' => $name];
        if ($entryMode !== '') {
            $definition['entry_mode'] = $entryMode;
        }
        $prepared = $this->getWiki()->services->get(FormManager::class)->prepareData(['template' => [$definition]]);
        $field = reset($prepared);
        $this->assertInstanceOf(DateField::class, $field);

        return $field;
    }

    private function selected(string $html, string $select): string
    {
        $this->assertMatchesRegularExpression('/<select[^>]*name="[^"]*_' . $select . '"[^>]*>.*?<\/select>/s', $html);
        preg_match('/<select[^>]*name="[^"]*_' . $select . '"[^>]*>(.*?)<\/select>/s', $html, $block);
        preg_match('/<option value="([^"]*)"\s*selected/', $block[1], $option);

        return $option[1] ?? '';
    }

    public function testAnEventStartsAndEndsWithHoursAnHourApart(): void
    {
        $start = (string)$this->field('listedatedeb', 'bf_date_debut_evenement')->renderInputIfPermitted([]);
        $end = (string)$this->field('listedatefin', 'bf_date_fin_evenement')->renderInputIfPermitted([]);

        $this->assertSame('0', $this->selected($start, 'allday'));
        $this->assertSame('0', $this->selected($end, 'allday'));
        $startMinutes = (int)$this->selected($start, 'hour') * 60 + (int)$this->selected($start, 'minutes');
        $endMinutes = (int)$this->selected($end, 'hour') * 60 + (int)$this->selected($end, 'minutes');
        $this->assertSame(min(23, (int)date('G') + 1) * 60, $startMinutes);
        $this->assertSame(min($startMinutes + 60, 23 * 60 + 55), $endMinutes);
        $this->assertStringNotContainsString('select-time hide', $start);
    }

    public function testAnotherDateStartsAsAWholeDay(): void
    {
        $html = (string)$this->field('listedatedeb', 'bf_date_echeance')->renderInputIfPermitted([]);

        $this->assertSame('1', $this->selected($html, 'allday'));
        $this->assertStringContainsString('select-time hide', $html);
    }

    public function testTheFormOptionDecides(): void
    {
        $this->assertTrue($this->field('listedatedeb', 'bf_date_rdv', DateField::ENTRY_MODE_TIME)->entersTimeByDefault());
        $this->assertFalse($this->field('jour', 'bf_date_debut_evenement', DateField::ENTRY_MODE_ALL_DAY)->entersTimeByDefault());
        $this->assertSame('0', $this->selected((string)$this->field('listedatedeb', 'bf_date_rdv', DateField::ENTRY_MODE_TIME)->renderInputIfPermitted([]), 'allday'));
        $this->assertSame('1', $this->selected((string)$this->field('jour', 'bf_date_ouverture', DateField::ENTRY_MODE_ALL_DAY)->renderInputIfPermitted([]), 'allday'));
    }

    public function testAStoredWholeDayStaysAWholeDay(): void
    {
        $html = (string)$this->field('listedatedeb', 'bf_date_debut_evenement')->renderInputIfPermitted(['bf_date_debut_evenement' => '2026-11-03']);

        $this->assertSame('1', $this->selected($html, 'allday'));
    }

    public function testARefusedSubmissionKeepsTheHoursItWasGiven(): void
    {
        $html = (string)$this->field('listedatedeb', 'bf_date_echeance')->renderInputIfPermitted([
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
