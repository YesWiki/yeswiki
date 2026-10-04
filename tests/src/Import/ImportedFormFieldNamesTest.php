<?php

namespace YesWiki\Test\Import;

use YesWiki\Content\Service\FormManager;
use YesWiki\Import\Service\YesWikiToYesWikiImporter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A form copied from another wiki goes through the same field-name check as one saved in the form editor (GHSA-hgxv-w4r8-8877). */
class ImportedFormFieldNamesTest extends YesWikiTestCase
{
    private const FORM_ID = 8651;
    private const VALID = '[{"type": "texte", "name": "bf_titre", "label": "Titre"},{"type": "texte", "name": "bf_lieu-dit", "label": "Lieu"}]';
    private const INVALID = '[{"type": "texte", "name": "bf_titre", "label": "Titre"},{"type": "texte", "name": "bf_x`y", "label": "Robot"}]';

    private FormManager $forms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forms = $this->getWiki()->services->get(FormManager::class);
        $this->forms->clear(self::FORM_ID);
        if ($this->forms->getOne(self::FORM_ID)) {
            $this->forms->delete(self::FORM_ID);
        }
    }

    protected function tearDown(): void
    {
        $this->forms->clear(self::FORM_ID);
        if ($this->forms->getOne(self::FORM_ID)) {
            $this->forms->delete(self::FORM_ID);
        }
        parent::tearDown();
    }

    private function importer(string $template, bool $localFormExists = false): YesWikiToYesWikiImporter
    {
        $importer = (new \ReflectionClass(YesWikiToYesWikiImporter::class))->newInstanceWithoutConstructor();
        $set = function (string $property, mixed $value) use ($importer): void {
            (new \ReflectionProperty(YesWikiToYesWikiImporter::class, $property))->setValue($importer, $value);
        };
        $set('formManager', $this->forms);
        $set('config', ['formId' => (string)self::FORM_ID, 'syncMode' => 'source_of_truth']);
        $set('remoteForm', ['label' => 'ImportedFormFieldNamesTest', 'template' => $template]);
        $set('localFormExists', $localFormExists);

        return $importer;
    }

    public function testAFormWithValidFieldNamesIsCreated(): void
    {
        ob_start();
        $this->importer(self::VALID)->syncFormModel();
        ob_end_clean();

        $this->forms->clear(self::FORM_ID);
        $this->assertNotEmpty($this->forms->getOne(self::FORM_ID));
    }

    public function testAFormWithAnInvalidFieldNameIsRefused(): void
    {
        try {
            $this->importer(self::INVALID)->syncFormModel();
            $this->fail('the form was accepted');
        } catch (\Exception $refused) {
            $this->assertStringContainsString('bf_x`y', $refused->getMessage());
        }

        $this->forms->clear(self::FORM_ID);
        $this->assertEmpty($this->forms->getOne(self::FORM_ID));
    }

    public function testAMirroredFormIsNotUpdatedWithAnInvalidFieldName(): void
    {
        ob_start();
        $this->importer(self::VALID)->syncFormModel();
        ob_end_clean();

        try {
            $this->importer(self::INVALID, true)->syncFormModel();
            $this->fail('the update was accepted');
        } catch (\Exception $refused) {
            $this->assertStringContainsString('bf_x`y', $refused->getMessage());
        }

        $this->forms->clear(self::FORM_ID);
        $names = array_map(fn ($field) => $field->getPropertyName(), $this->forms->getOne(self::FORM_ID)['prepared'] ?? []);
        $this->assertNotContains('bf_x`y', $names);
    }
}
