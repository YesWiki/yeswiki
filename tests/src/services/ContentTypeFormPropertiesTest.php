<?php

namespace YesWiki\Test\Content\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The form properties (ADR-0010) of a built-in Content type.
 *
 * Two things went wrong once the Page, User and File forms existed. They inherited the
 * bazar default title template `{{bf_titre}}`, a field none of them has, so every page
 * listed with an empty title. And they carried `entry_creates_user`/`entry_bookmarklet`,
 * which describe visitor submissions to a webmaster's form and mean nothing for a page,
 * an account or an uploaded file.
 */
class ContentTypeFormPropertiesTest extends YesWikiTestCase
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

    /** @return array<string, mixed> */
    private function builtInForm(string $contentType): array
    {
        $form = $this->getWiki()->services->get(FormManager::class)->getByContentType($contentType);
        $this->assertNotNull($form, "the {$contentType} form should exist -- run ./yeswicli migrate");

        return $form;
    }

    private function firstFreeFormId(FormManager $formManager): string
    {
        $id = 9700;
        while ($formManager->getOne((string)$id) !== null) {
            $id++;
        }

        return (string)$id;
    }

    /** @return array<string, list<string>> */
    public static function builtInTitleTemplates(): array
    {
        return [
            'a page is named by its title' => [ContentTypeSchema::TYPE_PAGE, '{{title}}'],
            'an account by its username' => [ContentTypeSchema::TYPE_USER, '{{username}}'],
            'a file by the name it was uploaded under' => [ContentTypeSchema::TYPE_FILE, '{{original_filename}}'],
        ];
    }

    #[DataProvider('builtInTitleTemplates')]
    public function testABuiltInFormNamesItselfWithOneOfItsOwnFields(string $contentType, string $expected): void
    {
        $form = $this->builtInForm($contentType);

        $this->assertSame($expected, $form['entry_title_template']);
        $this->assertNotSame(
            FormPropertiesService::DEFAULT_TITLE_TEMPLATE,
            $form['entry_title_template'],
            'bf_titre is a bazar convention: no built-in Content type has such a field'
        );

        // the template must name a field the form actually has, or every title is empty
        $this->assertContains(
            trim($expected, '{}'),
            array_column($form['template'], 'name')
        );
    }

    /** An ordinary bazar form keeps the historical convention as its default. */
    public function testAnOrdinaryFormStillDefaultsToTheBazarConvention(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $id = $this->firstFreeFormId($formManager);
        $this->assertSame(0, $formManager->create([
            'id' => (string)$id,
            'label' => 'ContentTypeFormPropertiesTest ordinary',
            'template' => [['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre']],
        ]));
        self::$createdFormIds[] = (string)$id;

        $created = $formManager->getOne((string)$id);
        $this->assertNotNull($created);
        $this->assertSame(FormPropertiesService::DEFAULT_TITLE_TEMPLATE, $created['entry_title_template']);
    }

    #[DataProvider('builtInTitleTemplates')]
    public function testABuiltInFormCarriesNoEntryOnlyProperty(string $contentType, string $unusedTitleTemplate): void
    {
        $form = $this->builtInForm($contentType);

        $this->assertArrayNotHasKey('entry_creates_user', $form);
        $this->assertArrayNotHasKey('entry_bookmarklet', $form);
    }

    /**
     * Both halves of the guarantee, on the pure function: a built-in type drops them
     * however they arrived, an ordinary form keeps them.
     */
    public function testEntryOnlyPropertiesAreStrippedForBuiltInTypesOnly(): void
    {
        $body = [
            'label' => 'whatever',
            'entry_creates_user' => ['name_field' => 'bf_titre'],
            'entry_bookmarklet' => ['url_field' => 'bf_url'],
            'entry_read_access' => '@admins',
        ];

        $stripped = ContentTypeSchema::stripInapplicableProperties($body, ContentTypeSchema::TYPE_PAGE);
        $this->assertArrayNotHasKey('entry_creates_user', $stripped);
        $this->assertArrayNotHasKey('entry_bookmarklet', $stripped);
        $this->assertSame('@admins', $stripped['entry_read_access'], 'an ACL property applies to a page');
        $this->assertSame('whatever', $stripped['label']);

        $this->assertSame(
            $body,
            ContentTypeSchema::stripInapplicableProperties($body, ContentTypeSchema::TYPE_ENTRY),
            'an ordinary bazar form is what these properties are for'
        );
        $this->assertSame(
            $body,
            ContentTypeSchema::stripInapplicableProperties($body, null),
            'a form with no declared Content type is an ordinary one'
        );
    }

    /**
     * The three destructive routes into a built-in form are closed.
     *
     * Deleting the Page form does not remove a data structure someone designed -- it
     * removes the schema every page in the wiki is edited and listed through, with no
     * route back except re-running the migration. Emptying it would mean deleting every
     * page. And creating an "entry" on it would make a fiche_bazar row carrying the Page
     * form's id, which belongs to no list at all, since the Page form owns the *untyped*
     * rows.
     */
    #[DataProvider('builtInTitleTemplates')]
    public function testABuiltInFormCannotBeDeletedEmptiedOrFilledWithEntries(string $contentType, string $unusedTitleTemplate): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $form = $this->builtInForm($contentType);

        foreach ([
            'delete' => fn () => $formManager->delete($form['id']),
            'empty' => fn () => $formManager->clear($form['id']),
            'create an entry on' => fn () => $wiki->services->get(EntryManager::class)->create($form['id'], ['antispam' => 1]),
        ] as $operation => $attempt) {
            try {
                $attempt();
                $this->fail("it should not be possible to {$operation} the {$contentType} form");
            } catch (\Exception $e) {
                $this->assertStringContainsString($contentType, $e->getMessage());
            }
        }

        $this->assertNotNull(
            $formManager->getByContentType($contentType),
            'the form must still be there after every refused attempt'
        );
    }

    /** Writing one to a built-in form does not make it stick. */
    public function testEntryOnlyPropertiesCannotBeWrittenOntoABuiltInForm(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $form = $this->builtInForm(ContentTypeSchema::TYPE_PAGE);

        $formManager->update([
            'id' => $form['id'],
            'label' => $form['label'],
            'template' => $form['template'],
            'entry_creates_user' => ['name_field' => 'title', 'email_field' => 'bf_mail'],
            'entry_bookmarklet' => ['url_field' => 'bf_url'],
        ]);

        $stored = $formManager->getByContentType(ContentTypeSchema::TYPE_PAGE);
        $this->assertNotNull($stored);
        $this->assertArrayNotHasKey('entry_creates_user', $stored);
        $this->assertArrayNotHasKey('entry_bookmarklet', $stored);
        $this->assertSame($form['entry_title_template'], $stored['entry_title_template']);
    }
}
