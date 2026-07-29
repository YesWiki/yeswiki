<?php

namespace YesWiki\Test\Content\Entity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Content\Entity\ContentTypeSchema;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 10: Page, User and File are forms whose core fields cannot be deleted or
 * retyped, while everything else about them stays the webmaster's to change.
 *
 * `enforce()` is the whole guarantee in one pure function, which is why it is tested
 * here without a wiki: every template write vector the ticket names -- the designer, the
 * API, CSV import, duplication, a hand-edited template -- reaches storage through it.
 */
class ContentTypeSchemaTest extends TestCase
{
    public function testAnOrdinaryFormTemplateIsLeftCompletelyAlone(): void
    {
        $template = [
            ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
            ['type' => 'textelong', 'name' => 'bf_description', 'label' => 'Description'],
        ];

        $this->assertSame($template, ContentTypeSchema::enforce($template, ContentTypeSchema::TYPE_ENTRY));
        $this->assertSame($template, ContentTypeSchema::enforce($template, null));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function mandatoryStructures(): array
    {
        return [
            'page' => [ContentTypeSchema::TYPE_PAGE, ['title', 'content', 'keywords']],
            'user' => [ContentTypeSchema::TYPE_USER, ['username', 'password', 'email']],
            'file' => [ContentTypeSchema::TYPE_FILE, ['original_filename', 'stored_filename', 'uploaded_from']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('mandatoryStructures')]
    public function testAnEmptyTemplateGetsTheWholeMandatoryStructure(string $type, array $expected): void
    {
        $enforced = ContentTypeSchema::enforce([], $type);

        $this->assertSame($expected, array_column($enforced, 'name'));
        foreach ($enforced as $field) {
            $this->assertNotEmpty($field['type'], "{$field['name']} must have a type");
        }
    }

    /** @param list<string> $expected */
    #[DataProvider('mandatoryStructures')]
    public function testDeletingALockedFieldPutsItBack(string $type, array $expected): void
    {
        // a webmaster (or an API client, or a CSV import) submits a template with the
        // core structure stripped out
        $enforced = ContentTypeSchema::enforce(
            [['type' => 'texte', 'name' => 'my_own_field', 'label' => 'Mine']],
            $type
        );

        $names = array_column($enforced, 'name');
        foreach ($expected as $locked) {
            $this->assertContains($locked, $names, "$locked must be restored");
        }
        $this->assertContains('my_own_field', $names, 'the webmaster field must survive the repair');
    }

    public function testRetypingALockedFieldIsReverted(): void
    {
        $enforced = ContentTypeSchema::enforce(
            [['type' => 'hidden', 'name' => 'password', 'label' => 'Mot de passe']],
            ContentTypeSchema::TYPE_USER
        );

        $password = array_values(array_filter($enforced, fn ($f) => $f['name'] === 'password'));
        $this->assertCount(1, $password);
        $this->assertSame('mot_de_passe', $password[0]['type'], 'a locked field keeps its declared type');
    }

    /**
     * The other half of the deal: locked does not mean frozen. If this stops being true
     * the designer becomes a lie -- the ticket asks for lock badges on otherwise
     * ordinary fields, not for read-only rows.
     */
    public function testLabelHelpTextAclAndOrderAreTheWebmastersToChange(): void
    {
        $template = [
            ['type' => 'texte', 'name' => 'my_own_field', 'label' => 'Mine'],
            [
                'type' => 'textelong',
                'name' => 'content',
                'label' => 'Le texte de la page',
                'hint' => 'Markdown accepté',
                'read_access' => '@admins',
                'write_access' => '@admins',
            ],
            ['type' => 'texte', 'name' => 'title', 'label' => 'Intitulé'],
            ['type' => 'tags', 'name' => 'keywords', 'label' => 'Thèmes'],
        ];

        $enforced = ContentTypeSchema::enforce($template, ContentTypeSchema::TYPE_PAGE);

        $this->assertSame($template, $enforced, 'a complete template must survive untouched, order included');
    }

    public function testAMissingLockedFieldIsPrependedSoTheCoreStructureLeads(): void
    {
        $enforced = ContentTypeSchema::enforce(
            [
                ['type' => 'texte', 'name' => 'my_own_field', 'label' => 'Mine'],
                ['type' => 'textelong', 'name' => 'content', 'label' => 'Contenu'],
            ],
            ContentTypeSchema::TYPE_PAGE
        );

        // title and keywords were missing; they come back at the front rather than
        // being buried under the webmaster's fields
        $this->assertSame(['title', 'keywords', 'my_own_field', 'content'], array_column($enforced, 'name'));
    }

    public function testADuplicatedLockedFieldIsCollapsedToTheFirst(): void
    {
        $enforced = ContentTypeSchema::enforce(
            [
                ['type' => 'texte', 'name' => 'title', 'label' => 'Kept'],
                ['type' => 'texte', 'name' => 'title', 'label' => 'Dropped'],
                ['type' => 'textelong', 'name' => 'content', 'label' => 'Contenu'],
                ['type' => 'tags', 'name' => 'keywords', 'label' => 'Mots clés'],
            ],
            ContentTypeSchema::TYPE_PAGE
        );

        $titles = array_values(array_filter($enforced, fn ($f) => $f['name'] === 'title'));
        $this->assertCount(1, $titles);
        $this->assertSame('Kept', $titles[0]['label']);
    }

    public function testIsLockedOnlyAnswersYesForTheTypesOwnCoreFields(): void
    {
        $this->assertTrue(ContentTypeSchema::isLocked(ContentTypeSchema::TYPE_PAGE, 'title'));
        $this->assertFalse(ContentTypeSchema::isLocked(ContentTypeSchema::TYPE_PAGE, 'password'));
        $this->assertTrue(ContentTypeSchema::isLocked(ContentTypeSchema::TYPE_USER, 'password'));
        $this->assertFalse(ContentTypeSchema::isLocked(ContentTypeSchema::TYPE_ENTRY, 'title'));
        $this->assertFalse(ContentTypeSchema::isLocked(null, 'title'));
    }

    public function testEnforceIsIdempotent(): void
    {
        foreach (array_column(self::mandatoryStructures(), 0) as $type) {
            $once = ContentTypeSchema::enforce([], $type);
            $this->assertSame($once, ContentTypeSchema::enforce($once, $type), "not idempotent for {$type}");
        }
    }
}
