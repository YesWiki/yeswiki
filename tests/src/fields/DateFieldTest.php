<?php

namespace YesWiki\Test\Core\Field;

require_once 'tests/YesWikiTestCase.php';

use YesWiki\Content\Field\DateField;
use YesWiki\Content\Service\EntryDateService;
use YesWiki\Test\Core\YesWikiTestCase;

/**
 * Regression test for ticket 24 (bazar absorbed into core): a blanket namespace rewrite (YesWiki\Bazar\Service\DateService -> YesWiki\Kernel\Service\DateService) collapsed DateField's/FileField's deliberate disambiguation between bazar's own DateService (renamed EntryDateService, since it collided with an unrelated, pre-existing core DateService) and that pre-existing core DateService (timezone formatting only).
 */
class DateFieldTest extends YesWikiTestCase
{
    private function buildDateField(string $propertyName): DateField
    {
        $wiki = $this->getWiki();

        $values = [
            'date',
            $propertyName,
            'Date field under test',
            '',
            '',
            '',
            '',
            '',
            '0',
            '0',
            '',
            '',
            '',
        ];

        return new DateField($values, $wiki->services);
    }

    public function testEntryDateServiceHasTheMethodsDateFieldAndFileFieldCall()
    {
        $entryDateService = $this->getWiki()->services->get(EntryDateService::class);
        $this->assertInstanceOf(EntryDateService::class, $entryDateService);

        $reflection = new \ReflectionClass(EntryDateService::class);
        $this->assertTrue($reflection->hasMethod('canRegisterMultipleEntries'));
        $this->assertTrue($reflection->hasMethod('followId'));
        $this->assertTrue($reflection->hasMethod('isLegacyRecurrenceChild'));
    }

    public function testFormatValuesBeforeSaveOnEndDatePropertyDoesNotFatal()
    {
        $field = $this->buildDateField('bf_date_fin_evenement');

        $entry = [
            'tag' => 'DateFieldTestEntry',
            'bf_date_fin_evenement' => '2024-01-01 10:00:00',
        ];

        $result = $field->formatValuesBeforeSave($entry);

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

        $output = $reflection->invoke($field, $entry);

        $this->assertIsString($output);
    }
}
