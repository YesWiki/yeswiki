<?php

namespace YesWiki\Test\Content\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 10 asks for each write vector to be tested, not just the designer. Every one of
 * them -- the designer's POST, the API, an import, a duplication, a hand-edited template
 * -- reaches storage through FormManager::create()/update(), so these exercise the real
 * service rather than the pure function ContentTypeSchemaTest already covers.
 */
class FormLockedFieldsTest extends YesWikiTestCase
{
    /** @var list<string> */
    private static array $createdFormIds = [];

    public static function tearDownAfterClass(): void
    {
        $formManager = self::getWiki()->services->get(FormManager::class);
        foreach (self::$createdFormIds as $id) {
            try {
                $formManager->delete($id);
            } catch (\Throwable $e) {
                // best effort: the suite shares the developer's database
            }
        }
        self::$createdFormIds = [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function createForm(array $data): array
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        // create() returns a save status, not the new form, and picks its own id when the
        // requested one is taken -- so claim a free id first and keep hold of it
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

    public function testCreatingAPageTypeFormGetsTheMandatoryStructure(): void
    {
        $form = $this->createForm([
            'label' => 'FormLockedFieldsTest page type',
            ContentTypeSchema::CONTENT_TYPE => ContentTypeSchema::TYPE_PAGE,
            'template' => [],
        ]);

        $this->assertSame(
            ['title', 'content', 'keywords'],
            array_column($form['template'], 'name')
        );
    }

    public function testAnOrdinaryFormIsUnaffected(): void
    {
        $form = $this->createForm([
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
        $form = $this->createForm([
            'label' => 'FormLockedFieldsTest strip',
            ContentTypeSchema::CONTENT_TYPE => ContentTypeSchema::TYPE_USER,
            'template' => [],
        ]);

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
    }

    public function testRetypingALockedFieldThroughUpdateIsReverted(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $form = $this->createForm([
            'label' => 'FormLockedFieldsTest retype',
            ContentTypeSchema::CONTENT_TYPE => ContentTypeSchema::TYPE_USER,
            'template' => [],
        ]);

        $formManager->update([
            'id' => $form['id'],
            'label' => $form['label'],
            // a hidden field would render nothing and read back freely
            'template' => [['type' => 'hidden', 'name' => 'password', 'label' => 'Mot de passe']],
        ]);

        $updated = $formManager->getOne($form['id']);
        $this->assertNotNull($updated);
        $password = array_values(array_filter($updated['template'], fn ($f) => ($f['name'] ?? '') === 'password'));
        $this->assertCount(1, $password);
        $this->assertSame('mot_de_passe', $password[0]['type']);
    }

    /**
     * Retyping the *form* would be the way around all of the above: declare a User form
     * to be an ordinary entry form, and its core fields stop being locked.
     */
    public function testAFormsContentTypeCannotBeChangedAfterCreation(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $form = $this->createForm([
            'label' => 'FormLockedFieldsTest immutable type',
            ContentTypeSchema::CONTENT_TYPE => ContentTypeSchema::TYPE_PAGE,
            'template' => [],
        ]);

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
    }

    public function testAnUnknownContentTypeFallsBackToTheOrdinaryForm(): void
    {
        $form = $this->createForm([
            'label' => 'FormLockedFieldsTest unknown type',
            ContentTypeSchema::CONTENT_TYPE => 'not-a-content-type',
            'template' => [['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre']],
        ]);

        $this->assertSame(ContentTypeSchema::TYPE_ENTRY, $form[ContentTypeSchema::CONTENT_TYPE]);
        $this->assertSame(['bf_titre'], array_column($form['template'], 'name'));
    }
}
