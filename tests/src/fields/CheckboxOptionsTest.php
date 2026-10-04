<?php

namespace YesWiki\Test\Core\Field;

require_once 'tests/YesWikiTestCase.php';

use YesWiki\Content\Field\CheckboxListField;
use YesWiki\Test\Core\YesWikiTestCase;

/** A checkbox field offers its options sorted and limited, and never loses a recorded value. */
class CheckboxOptionsTest extends YesWikiTestCase
{
    private const OPTIONS = ['b' => 'Bravo', 'a' => 'Écho', 'c' => 'Alpha', 'd' => 'Delta'];

    /** @param array<int, string> $settings */
    private function field(array $settings = []): CheckboxListField
    {
        $values = array_replace(array_fill(0, 19, ''), [0 => 'checkbox', 1 => 'ListeAbsente', 2 => 'Choix', 6 => 'bf_choix'], $settings);
        $field = new CheckboxListField($values, $this->getWiki()->services);
        (new \ReflectionProperty($field, 'options'))->setValue($field, self::OPTIONS);

        return $field;
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<int|string, mixed>
     */
    private function inputOptions(CheckboxListField $field, array $entry = []): array
    {
        return (new \ReflectionMethod($field, 'getInputOptions'))->invoke($field, $entry);
    }

    public function testWithoutSettingsTheOptionsKeepTheirOrder(): void
    {
        $this->assertSame(self::OPTIONS, $this->inputOptions($this->field()));
    }

    public function testOptionsAreSortedOnTheirLabelIgnoringAccents(): void
    {
        $this->assertSame(['c', 'b', 'd', 'a'], array_keys($this->inputOptions($this->field([16 => 'label']))));
        $this->assertSame(['a', 'd', 'b', 'c'], array_keys($this->inputOptions($this->field([16 => 'label', 17 => 'desc']))));
    }

    public function testOptionsAreSortedOnTheirKey(): void
    {
        $this->assertSame(['d', 'c', 'b', 'a'], array_keys($this->inputOptions($this->field([16 => 'id', 17 => 'desc']))));
    }

    public function testALimitKeepsTheCheckedOptionsBeyondIt(): void
    {
        $field = $this->field([16 => 'id', 18 => '2']);

        $this->assertSame(['a', 'b'], array_keys($this->inputOptions($field)));
        $this->assertSame(['a', 'b', 'd'], array_keys($this->inputOptions($field, ['bf_choix' => 'd'])));
    }

    public function testARecordedValueTheOptionsNoLongerHoldIsStillOffered(): void
    {
        $options = $this->inputOptions($this->field(), ['bf_choix' => 'a,disparue']);

        $this->assertSame('disparue', $options['disparue'] ?? null);
        $this->assertStringContainsString('name="bf_choix[disparue]"', (string)(new \ReflectionMethod(CheckboxListField::class, 'renderInput'))->invoke($this->field(), ['bf_choix' => 'a,disparue']));
    }
}
