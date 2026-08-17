<?php

namespace YesWiki\Test\Content\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 10 asks for each write vector to be tested, not just the designer. */
class FormLockedFieldsTest extends YesWikiTestCase
{
    /**
     * @var list<string>
     */
    private static array $createdFormIds = [];

    public static function tearDownAfterClass(): void
    {
        $formManager = self::getWiki()->services->get(FormManager::class);
        foreach (self::$createdFormIds as $id) {
            try {
                $formManager->delete($id);
            } catch (\Throwable $e) {
            }
        }
        self::$createdFormIds = [];
    }

    /**
     * The one form describing a built-in Content type, as created by CreateContentTypeForms.
     *
     * @return array<string, mixed>
     */
    private function builtInForm(string $contentType): array
    {
        $form = $this->getWiki()->services->get(FormManager::class)->getByContentType($contentType);
        $this->assertNotNull($form, "the {$contentType} form should exist -- run ./yeswicli migrate");

        return $form;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function createOrdinaryForm(array $data): array
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        $id = $this->firstFreeFormId($formManager);
        $data['id'] = $id;

        $this->assertSame(0, $formManager->create($data), 'the form should have been created');
        self::$createdFormIds[] = $id;

        $form = $formManager->getOne($id);
        $this->assertNotNull($form, 'the created form should be readable back');

        return $form;
    }

    private function firstFreeFormId(FormManager $formManager): string
    {
        $id = 9000;
        while ($formManager->getOne((string)$id) !== null) {
            $id++;
        }

        return (string)$id;
    }

    /**
     * Restore a built-in form's template after a test has mangled it.
     *
     * @param array<string, mixed> $form
     */
    private function restoreTemplate(array $form): void
    {
        $this->getWiki()->services->get(FormManager::class)->update([
            'id' => $form['id'],
            'label' => $form['label'],
            'template' => $form['template'],
        ]);
    }

    public function testTheBuiltInPageFormCarriesTheMandatoryStructure(): void
    {
        $form = $this->builtInForm(ContentTypeSchema::TYPE_PAGE);

        $names = array_column($form['template'], 'name');
        foreach (['title', 'content', 'keywords'] as $locked) {
            $this->assertContains($locked, $names);
        }
    }

    public function testAnOrdinaryFormIsUnaffected(): void
    {
        $form = $this->createOrdinaryForm([
            'label' => 'FormLockedFieldsTest ordinary',
            'template' => [['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre']],
        ]);

        $this->assertSame(['bf_titre'], array_column($form['template'], 'name'));
        $this->assertSame(ContentTypeSchema::TYPE_ENTRY, $form[ContentTypeSchema::CONTENT_TYPE]);
    }

    /** The direct-template-edit and API vectors: submit a template with the core removed. */
    public function testUpdatingWithTheLockedFieldsStrippedRestoresThem(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $form = $this->builtInForm(ContentTypeSchema::TYPE_USER);

        try {
            $formManager->update([
                'id' => $form['id'],
                'label' => $form['label'],
                'template' => [['type' => 'texte', 'name' => 'address', 'label' => 'Adresse']],
            ]);

            $updated = $formManager->getOne($form['id']);
            $this->assertNotNull($updated);
            $names = array_column($updated['template'], 'name');
            foreach (['username', 'password', 'email'] as $locked) {
                $this->assertContains($locked, $names, "$locked must survive a template that omits it");
            }
            $this->assertContains('address', $names, 'the webmaster-added field must survive too');
        } finally {
            $this->restoreTemplate($form);
        }
    }

    public function testRetypingALockedFieldThroughUpdateIsReverted(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $form = $this->builtInForm(ContentTypeSchema::TYPE_USER);

        try {
            $formManager->update([
                'id' => $form['id'],
                'label' => $form['label'],

                'template' => [['type' => 'hidden', 'name' => 'password', 'label' => 'Mot de passe']],
            ]);

            $updated = $formManager->getOne($form['id']);
            $this->assertNotNull($updated);
            $password = array_values(array_filter($updated['template'], fn ($f) => ($f['name'] ?? '') === 'password'));
            $this->assertCount(1, $password);
            $this->assertSame('mot_de_passe', $password[0]['type']);
        } finally {
            $this->restoreTemplate($form);
        }
    }

    /**
     * Retyping the *form* would be the way around all of the above: declare the User form to be an ordinary entry form, and its core fields stop being locked.
     */
    public function testAFormsContentTypeCannotBeChangedAfterCreation(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $form = $this->builtInForm(ContentTypeSchema::TYPE_PAGE);

        try {
            $formManager->update([
                'id' => $form['id'],
                'label' => $form['label'],
                ContentTypeSchema::CONTENT_TYPE => ContentTypeSchema::TYPE_ENTRY,
                'template' => [['type' => 'texte', 'name' => 'whatever', 'label' => 'Whatever']],
            ]);

            $stored = $formManager->getOne($form['id']);
            $this->assertNotNull($stored);
            $this->assertSame(ContentTypeSchema::TYPE_PAGE, $stored[ContentTypeSchema::CONTENT_TYPE]);
            $this->assertContains('title', array_column($stored['template'], 'name'));
        } finally {
            $this->restoreTemplate($form);
        }
    }

    public function testAnUnknownContentTypeFallsBackToTheOrdinaryForm(): void
    {
        $form = $this->createOrdinaryForm([
            'label' => 'FormLockedFieldsTest unknown type',
            ContentTypeSchema::CONTENT_TYPE => 'not-a-content-type',
            'template' => [['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre']],
        ]);

        $this->assertSame(ContentTypeSchema::TYPE_ENTRY, $form[ContentTypeSchema::CONTENT_TYPE]);
        $this->assertSame(['bf_titre'], array_column($form['template'], 'name'));
    }

    /**
     * There is exactly one form per built-in type: a second one would leave getByContentType() choosing arbitrarily between them.
     */
    public function testASecondFormOfABuiltInTypeIsRefused(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/exactly one per Content type/');
        $formManager->create([
            'id' => $this->firstFreeFormId($formManager),
            'label' => 'FormLockedFieldsTest second page form',
            ContentTypeSchema::CONTENT_TYPE => ContentTypeSchema::TYPE_PAGE,
            'template' => [],
        ]);
    }
}
