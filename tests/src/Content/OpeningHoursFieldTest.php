<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\TestCase;
use YesWiki\Content\Field\OpeningHoursField;

/** The editor starts from the rows the stored value describes. */
class OpeningHoursFieldTest extends TestCase
{
    public function testEachRuleBecomesARowOfDaysAndTimes(): void
    {
        $this->assertSame([
            ['days' => ['Mo', 'Tu'], 'hours' => [['start' => '09:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '18:00']]],
            ['days' => ['Sa'], 'hours' => [['start' => '10:00', 'end' => '12:00']]],
        ], OpeningHoursField::intervals('Mo,Tu 09:00-12:00,14:00-18:00;Sa 10:00-12:00'));
    }

    public function testAnEmptyValueStartsWithOneBlankRow(): void
    {
        $this->assertSame([['days' => [], 'hours' => [['start' => '', 'end' => '']]]], OpeningHoursField::intervals(''));
    }

    public function testADayWithoutTimesKeepsAnEmptyTime(): void
    {
        $this->assertSame([['days' => ['Su'], 'hours' => [['start' => '', 'end' => '']]]], OpeningHoursField::intervals('Su'));
    }
}
