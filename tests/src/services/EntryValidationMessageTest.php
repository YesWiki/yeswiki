<?php

namespace YesWiki\Test\Content\Service;

use YesWiki\Content\Exception\EntryValidationException;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A required field left empty is the visitor's problem, and is reported as one. */
class EntryValidationMessageTest extends YesWikiTestCase
{
    private static ?string $formId = null;

    public static function tearDownAfterClass(): void
    {
        if (self::$formId !== null) {
            self::getWiki()->services->get(FormManager::class)->delete(self::$formId);
            self::$formId = null;
        }
    }

    private function formWithARequiredField(): string
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        if (self::$formId === null) {
            $id = 9900;
            while ($formManager->getOne((string)$id) !== null) {
                $id++;
            }
            $this->assertSame(0, $formManager->create([
                'id' => (string)$id,
                'label' => 'EntryValidationMessageTest',
                'entry_title_template' => '{{bf_titre}}',
                'template' => [
                    ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                    ['type' => 'champs_mail', 'name' => 'bf_mail', 'label' => 'Adresse électronique', 'required' => '1'],
                ],
            ]));
            self::$formId = (string)$id;
        }

        return self::$formId;
    }

    public function testAMissingRequiredFieldIsAValidationErrorNamingItsLabel(): void
    {
        $formId = $this->formWithARequiredField();

        try {
            $this->getWiki()->services->get(EntryManager::class)->create($formId, [
                'antispam' => 1,
                'bf_titre' => 'Une fiche sans adresse',
            ]);
            $this->fail('a required field left empty must stop the save');
        } catch (EntryValidationException $e) {
            $this->assertStringContainsString(
                'Adresse électronique',
                $e->getMessage(),
                "the visitor is told which field to fill, by the label they can see -- not 'bf_mail'"
            );
            $this->assertStringNotContainsString('bf_mail', $e->getMessage());
        }
    }

    /**
     * Typed, so the controller can tell it apart from the failures that really are the site's problem and still deserve "contact the administrator".
     */
    public function testItIsNotJustAnyException(): void
    {
        $formId = $this->formWithARequiredField();
        $entryManager = $this->getWiki()->services->get(EntryManager::class);

        $this->expectException(EntryValidationException::class);
        $entryManager->create($formId, ['antispam' => 1, 'bf_mail' => 'sans-titre@example.org']);
    }
}
