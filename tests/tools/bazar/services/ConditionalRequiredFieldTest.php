<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Exception\RequiredFieldsException;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A required field the browser hides behind a condition must not block the save.
 */
class ConditionalRequiredFieldTest extends YesWikiTestCase
{
    private const FORM_ID = '999906';

    private $entryManager;
    private $formManager;
    private $tags = [];

    protected function setUp(): void
    {
        $wiki = $this->getWiki();
        $this->formManager = $wiki->services->get(FormManager::class);
        $this->entryManager = $wiki->services->get(EntryManager::class);
        $this->formManager->create([
            'bn_id_nature' => self::FORM_ID,
            'bn_label_nature' => 'Conditional required field test form',
            'bn_template' => implode("\n", [
                'texte***bf_titre***Titre*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'texte***bf_choix***Choix*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***',
                'conditionschecking***bf_choix==oui*** *** *** *** *** *** *** *** *** *** *** *** *** *** ***',
                'texte***bf_detail***Detail*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'labelhtml***</div><!-- Fin de condition-->*** *** ***false*** *** *** *** *** *** *** *** *** *** *** ***',
            ]),
            'bn_condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tags as $tag) {
            $this->entryManager->delete($tag, true);
        }
        $this->formManager->delete(self::FORM_ID);
    }

    private function create(string $tag, array $data): array
    {
        $entry = $this->entryManager->create(self::FORM_ID, array_merge([
            'antispam' => 1,
            'id_fiche' => $tag,
        ], $data));
        $this->tags[] = $entry['id_fiche'];

        return $entry;
    }

    public function testAnEmptyRequiredFieldBehindAFalseConditionIsAccepted()
    {
        $entry = $this->create('ConditionalRequiredFieldHidden', [
            'bf_titre' => 'Condition fausse',
            'bf_choix' => 'non',
        ]);

        $this->assertSame('ConditionalRequiredFieldHidden', $entry['id_fiche']);
        $this->assertArrayNotHasKey('bf_detail', $entry);
    }

    public function testAValueLeftBehindByAFalseConditionIsNotSaved()
    {
        $entry = $this->create('ConditionalRequiredFieldLeftover', [
            'bf_titre' => 'Valeur residuelle',
            'bf_choix' => 'non',
            'bf_detail' => 'saisi puis masque',
        ]);

        $this->assertArrayNotHasKey('bf_detail', $entry);
    }

    public function testAnEmptyRequiredFieldBehindATrueConditionIsRefused()
    {
        $this->expectException(RequiredFieldsException::class);

        try {
            $this->create('ConditionalRequiredFieldShown', [
                'bf_titre' => 'Condition vraie',
                'bf_choix' => 'oui',
                'bf_detail' => '',
            ]);
        } catch (RequiredFieldsException $e) {
            $this->assertSame(['bf_detail' => 'Detail'], $e->getFields());

            throw $e;
        }
    }
}
