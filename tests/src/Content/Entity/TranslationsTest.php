<?php

namespace YesWiki\Test\Content\Entity;

use PHPUnit\Framework\TestCase;
use YesWiki\Content\Entity\Translations;

require_once 'tests/YesWikiTestCase.php';

/** The translations a Content body carries: one store, addressed by path, applied on the way out. */
class TranslationsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function entry(): array
    {
        return [
            'tag' => 'UneFiche',
            'form_id' => '3',
            'bf_titre' => 'Le titre',
            'bf_description' => 'La description',
            Translations::BODY_KEY => [
                'en' => ['bf_titre' => 'The title'],
                'es' => ['bf_titre' => 'El título', 'bf_description' => 'La descripción'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function form(): array
    {
        return [
            'id' => '3',
            'label' => 'Agenda',
            'lang' => 'fr-FR',
            'template' => [
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'hint' => 'Le nom de l’événement'],
                ['type' => 'textelong', 'name' => 'bf_description', 'label' => 'Description'],
            ],
            Translations::BODY_KEY => [
                'en' => [
                    'label' => 'Calendar',
                    'template.bf_titre.label' => 'Title',
                    'template.bf_titre.hint' => 'The event name',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valueList(): array
    {
        return [
            'title' => 'Couleurs',
            'nodes' => [
                ['id' => 'rouge', 'label' => 'Rouge', 'children' => [
                    ['id' => 'carmin', 'label' => 'Carmin'],
                ]],
                ['id' => 'bleu', 'label' => 'Bleu'],
            ],
            Translations::BODY_KEY => [
                'en' => [
                    'title' => 'Colours',
                    'nodes.rouge.label' => 'Red',
                    'nodes.rouge.children.carmin.label' => 'Carmine',
                ],
            ],
        ];
    }

    public function testApplyOverlaysTheAskedLanguageAndRemovesTheStore(): void
    {
        $read = Translations::apply($this->entry(), 'es');

        $this->assertSame('El título', $read['bf_titre']);
        $this->assertSame('La descripción', $read['bf_description']);
        $this->assertArrayNotHasKey(Translations::BODY_KEY, $read);
    }

    /** The whole point: a half-translated entry falls back to the source, it does not go blank. */
    public function testAnUntranslatedFieldKeepsItsSourceText(): void
    {
        $read = Translations::apply($this->entry(), 'en');

        $this->assertSame('The title', $read['bf_titre']);
        $this->assertSame('La description', $read['bf_description']);
    }

    public function testAnEmptyTranslationNeverWinsOverTheSource(): void
    {
        $body = $this->entry();
        $body[Translations::BODY_KEY]['en']['bf_description'] = '';

        $this->assertSame('La description', Translations::apply($body, 'en')['bf_description']);
    }

    public function testAnUnknownLanguageLeavesEverySourceValueAlone(): void
    {
        $read = Translations::apply($this->entry(), 'de');

        $this->assertSame('Le titre', $read['bf_titre']);
        $this->assertSame('La description', $read['bf_description']);
        $this->assertArrayNotHasKey(Translations::BODY_KEY, $read);
    }

    public function testABodyWithNoTranslationsIsReturnedUntouched(): void
    {
        $body = ['bf_titre' => 'Le titre'];

        $this->assertSame($body, Translations::apply($body, 'en'));
    }

    public function testAPathReachesAFieldInsideAFormTemplateByItsName(): void
    {
        $read = Translations::apply($this->form(), 'en');

        $this->assertSame('Calendar', $read['label']);
        $this->assertSame('Title', $read['template'][0]['label']);
        $this->assertSame('The event name', $read['template'][0]['hint']);
        $this->assertSame('Description', $read['template'][1]['label']);
    }

    public function testAPathReachesANestedListNodeByItsId(): void
    {
        $read = Translations::apply($this->valueList(), 'en');

        $this->assertSame('Colours', $read['title']);
        $this->assertSame('Red', $read['nodes'][0]['label']);
        $this->assertSame('Carmine', $read['nodes'][0]['children'][0]['label']);
        $this->assertSame('Bleu', $read['nodes'][1]['label']);
    }

    /** A translation for a field that has since been deleted must not put the field back. */
    public function testAPathThatNamesNothingWritesNothing(): void
    {
        $body = $this->form();
        $body[Translations::BODY_KEY]['en']['template.bf_gone.label'] = 'Gone';

        $read = Translations::apply($body, 'en');

        $this->assertCount(2, $read['template']);
    }

    /** An entry may be translated into a field it has no value for yet. */
    public function testAMissingTopLevelKeyIsCreated(): void
    {
        $body = ['bf_titre' => 'Le titre', Translations::BODY_KEY => ['en' => ['bf_note' => 'A note']]];

        $this->assertSame('A note', Translations::apply($body, 'en')['bf_note']);
    }

    public function testAPathMayNotOverwriteAContainer(): void
    {
        $body = $this->form();
        $body[Translations::BODY_KEY]['en']['template'] = 'oops';

        $this->assertIsArray(Translations::apply($body, 'en')['template']);
    }

    public function testLanguagesListsOnlyTheOnesActuallyFilledIn(): void
    {
        $body = $this->entry();
        $body[Translations::BODY_KEY]['nl'] = [];

        $this->assertSame(['en', 'es'], Translations::languages($body));
    }

    public function testWithReplacesOneLanguageAndLeavesTheOthersAlone(): void
    {
        $body = Translations::with($this->entry(), 'en', ['bf_titre' => 'Another title']);

        $this->assertSame(['bf_titre' => 'Another title'], $body[Translations::BODY_KEY]['en']);
        $this->assertSame('El título', $body[Translations::BODY_KEY]['es']['bf_titre']);
    }

    public function testWithDropsBlankTextsAndThenTheEmptyLanguage(): void
    {
        $body = Translations::with($this->entry(), 'en', ['bf_titre' => '   ']);

        $this->assertArrayNotHasKey('en', $body[Translations::BODY_KEY]);
        $this->assertArrayHasKey('es', $body[Translations::BODY_KEY]);
    }

    public function testWithRemovesTheStoreOnceTheLastLanguageIsCleared(): void
    {
        $body = Translations::with($this->entry(), 'en', []);
        $body = Translations::with($body, 'es', []);

        $this->assertArrayNotHasKey(Translations::BODY_KEY, $body);
    }

    /** What keeps an ordinary edit from wiping the translations it never saw. */
    public function testCarriedOverMovesTheStoreOntoAPostedBody(): void
    {
        $posted = ['tag' => 'UneFiche', 'bf_titre' => 'Un autre titre'];

        $carried = Translations::carriedOver($this->entry(), $posted);

        $this->assertSame($this->entry()[Translations::BODY_KEY], $carried[Translations::BODY_KEY]);
    }

    public function testCarriedOverPrefersWhatTheTargetAlreadyBrings(): void
    {
        $posted = [Translations::BODY_KEY => ['en' => ['bf_titre' => 'Mine']]];

        $carried = Translations::carriedOver($this->entry(), $posted);

        $this->assertSame(['en' => ['bf_titre' => 'Mine']], $carried[Translations::BODY_KEY]);
    }

    public function testRenamedPathFollowsAFieldThatWasRenamed(): void
    {
        $body = Translations::renamedPath($this->entry(), 'bf_titre', 'bf_nom');

        $this->assertSame('The title', $body[Translations::BODY_KEY]['en']['bf_nom']);
        $this->assertArrayNotHasKey('bf_titre', $body[Translations::BODY_KEY]['en']);
    }

    public function testRenamedPathFollowsEverythingUnderTheRenamedPath(): void
    {
        $body = Translations::renamedPath($this->form(), 'template.bf_titre', 'template.bf_nom');

        $this->assertArrayHasKey('template.bf_nom.label', $body[Translations::BODY_KEY]['en']);
        $this->assertArrayHasKey('template.bf_nom.hint', $body[Translations::BODY_KEY]['en']);
    }

    public function testWithoutPathDropsAnAttributeInEveryLanguage(): void
    {
        $body = Translations::withoutPath($this->entry(), 'bf_titre');

        $this->assertArrayNotHasKey('en', $body[Translations::BODY_KEY]);
        $this->assertSame(['bf_description' => 'La descripción'], $body[Translations::BODY_KEY]['es']);
    }

    public function testPrunedForgetsTranslationsForWhatIsNoLongerThere(): void
    {
        $body = $this->form();
        $body[Translations::BODY_KEY]['en']['template.bf_gone.label'] = 'Gone';

        $pruned = Translations::pruned($body);

        $this->assertArrayNotHasKey('template.bf_gone.label', $pruned[Translations::BODY_KEY]['en']);
        $this->assertArrayHasKey('template.bf_titre.label', $pruned[Translations::BODY_KEY]['en']);
    }

    public function testSourceTextAtReadsThroughTemplatesAndNodes(): void
    {
        $this->assertSame('Titre', Translations::sourceTextAt($this->form(), 'template.bf_titre.label'));
        $this->assertSame('Carmin', Translations::sourceTextAt($this->valueList(), 'nodes.rouge.children.carmin.label'));
        $this->assertSame('', Translations::sourceTextAt($this->form(), 'template.bf_gone.label'));
    }

    public function testStripRemovesTheStoreAndNothingElse(): void
    {
        $stripped = Translations::strip($this->entry());

        $this->assertArrayNotHasKey(Translations::BODY_KEY, $stripped);
        $this->assertSame('Le titre', $stripped['bf_titre']);
    }
}
