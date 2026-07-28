<?php

namespace YesWiki\Test\Core\Field;

require_once 'tests/YesWikiTestCase.php';

use YesWiki\Content\Field\DateField;
use YesWiki\Content\Service\EntryDateService;
use YesWiki\Test\Core\YesWikiTestCase;

/**
 * Regression test for ticket 24 (bazar absorbed into core): a blanket namespace
 * rewrite (YesWiki\Bazar\Service\DateService -> YesWiki\Kernel\Service\DateService)
 * collapsed DateField's/FileField's deliberate disambiguation between bazar's own
 * DateService (renamed EntryDateService, since it collided with an unrelated,
 * pre-existing core DateService) and that pre-existing core DateService (timezone
 * formatting only). Both fields ended up calling methods (canRegisterMultipleEntries,
 * followId, isLegacyRecurrenceChild) that only exist on EntryDateService, through a
 * reference to the wrong class -- a fatal "call to undefined method" on every
 * recurring-event save. Caught by code review, not by this test suite (no test
 * previously exercised DateField's recurrence-tracking methods at all).
 */
class DateFieldTest extends YesWikiTestCase
{
    private function buildDateField(string $propertyName): DateField
    {
        $wiki = $this->getWiki();

        // BazarField's legacy 13-column positional array (FIELD_TYPE..FIELD_WRITE_ACCESS)
        $values = [
            'date', // FIELD_TYPE
            $propertyName, // FIELD_NAME
            'Date field under test', // FIELD_LABEL
            '', // FIELD_SIZE
            '', // FIELD_MAX_CHARS
            '', // FIELD_DEFAULT
            '', // 6
            '', // 7
            '0', // FIELD_REQUIRED
            '0', // FIELD_SEARCHABLE
            '', // FIELD_HINT
            '', // FIELD_READ_ACCESS
            '', // FIELD_WRITE_ACCESS
        ];

        return new DateField($values, $wiki->services);
    }

    public function testEntryDateServiceHasTheMethodsDateFieldAndFileFieldCall()
    {
        $entryDateService = $this->getWiki()->services->get(EntryDateService::class);

        $this->assertTrue(method_exists($entryDateService, 'canRegisterMultipleEntries'));
        $this->assertTrue(method_exists($entryDateService, 'followId'));
        $this->assertTrue(method_exists(EntryDateService::class, 'isLegacyRecurrenceChild'));
    }

    public function testFormatValuesBeforeSaveOnEndDatePropertyDoesNotFatal()
    {
        $field = $this->buildDateField('bf_date_fin_evenement');

        $entry = [
            'tag' => 'DateFieldTestEntry',
            'bf_date_fin_evenement' => '2024-01-01 10:00:00',
        ];

        // this call path is exactly what fataled with "call to undefined method"
        // when DateField referenced the wrong DateService class
        $result = $field->formatValuesBeforeSave($entry);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('bf_date_fin_evenement', $result);
    }

    public function testRenderStaticWithRecurrenceDataDoesNotFatal()
    {
        $field = $this->buildDateField('bf_date_fin_evenement');

        $entry = [
            'tag' => 'DateFieldTestEntry',
            'bf_date_fin_evenement' => '2024-01-01 10:00:00',
            'bf_date_fin_evenement_data' => '{"recurrentParentId":"SomeParentEntry"}',
        ];

        $reflection = new \ReflectionMethod($field, 'renderStatic');

        // exercises EntryDateService::isLegacyRecurrenceChild() -- also fatal before the fix
        $output = $reflection->invoke($field, $entry);

        $this->assertIsString($output);
    }
}
