<?php

namespace YesWiki\Test\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\FieldFactory;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * What each field type contributes to the search index (ticket 18 / ADR-0015).
 *
 * The index is built by *asking* the field, never by walking the body, and each field type's
 * answer is a decision rather than an accident:
 *
 * - **disclosure** -- an address in a wiki-wide index makes search an address-harvesting
 *   endpoint, and a password hash has no business being matchable;
 * - **envelope** -- a stored filename is a UUID and a date is a number, so indexing them
 *   recreates "search 2026, match everything edited this year", which is the very defect
 *   this ticket replaced;
 * - **keys, not labels** -- an enum stores a key and the label lives in the form, so
 *   indexing labels would make one designer edit invalidate every entry under the form.
 *
 * If someone later "fixes" one of these by making it contribute its value, this is what
 * says no.
 */
class SearchableTextTest extends YesWikiTestCase
{
    /**
     * A single prepared field of the given type.
     *
     * The factory takes the constructors' positional wire format, so the readable field
     * object goes through FormManager's own converter rather than being hand-positioned
     * here -- the positions are an implementation detail of each field class.
     */
    /** @param array<string, string> $extra */
    private function field(string $type, array $extra = []): BazarField
    {
        $wiki = $this->getWiki();
        $definition = array_merge([
            'type' => $type,
            'name' => 'bf_test',
            'label' => 'Un champ',
        ], $extra);

        $positional = $wiki->services->get(FormManager::class)->templateToPositionalList([$definition])[0];
        $field = $wiki->services->get(FieldFactory::class)->create($positional);
        $this->assertInstanceOf(BazarField::class, $field, "the field factory should build a '{$type}'");

        return $field;
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function contributesNothingProvider(): array
    {
        return [
            'email -- harvesting' => ['champs_mail', 'quelquun@example.com'],
            'password -- disclosure' => ['mot_de_passe', 'unehashquelconque'],
            'file -- a UUID filename' => ['fichier', 'MonFichier_20260101_abcdef.pdf'],
            'image -- a UUID filename' => ['image', 'MonImage_20260101_abcdef.jpg'],
            'date -- every year would match all' => ['listedatedeb', '2026-08-02'],
            'link -- mostly scheme and host' => ['lien_internet', 'https://example.org/page'],
        ];
    }

    #[DataProvider('contributesNothingProvider')]
    public function testSomeFieldTypesContributeNothing(string $type, string $value): void
    {
        $field = $this->field($type);

        $this->assertSame(
            '',
            $field->searchableText(['bf_test' => $value]),
            "a '{$type}' must put nothing in the search index"
        );
    }

    public function testATextFieldContributesItsValue(): void
    {
        $field = $this->field('texte');

        $this->assertSame('une valeur ordinaire', $field->searchableText(['bf_test' => 'une valeur ordinaire']));
    }

    /**
     * getValue() substitutes the field's default when the entry has no value, which is right
     * for an input and wrong here: it would index a value the Content does not have.
     */
    public function testAFieldDefaultIsNotIndexed(): void
    {
        $field = $this->field('texte', ['default' => 'valeurpardefaut']);

        $this->assertSame('', $field->searchableText([]), 'a default is not something the Content says');
    }

    public function testAProseFieldIsStrippedOfMarkupAndActions(): void
    {
        $field = $this->field('textelong', ['syntax' => 'wiki-textarea']);

        $text = $field->searchableText(['bf_test' => '**gras** {{entrylist id="1"}} du texte']);

        $this->assertStringContainsString('gras', $text);
        $this->assertStringContainsString('du texte', $text);
        $this->assertStringNotContainsString('entrylist', $text);
        $this->assertStringNotContainsString('**', $text);
    }

    /**
     * The one that matters at scale: a checkbox stores keys, and keys are what is indexed.
     * Labels are translated at query time instead (FormOptionTranslator), so relabelling an
     * option reindexes nothing.
     */
    public function testAnEnumContributesItsStoredKeysNotItsLabels(): void
    {
        $field = $this->field('liste', ['linked_object' => 'ListeNonExistante']);

        $this->assertSame('cle1', $field->searchableText(['bf_test' => 'cle1']));
    }

    /** A multi-valued field must not silently lose everything after the first value. */
    public function testAMultiValuedFieldContributesEveryValue(): void
    {
        $field = $this->field('texte');

        $text = $field->searchableText(['bf_test' => ['premier', 'deuxieme', 'troisieme']]);

        $this->assertStringContainsString('premier', $text);
        $this->assertStringContainsString('deuxieme', $text);
        $this->assertStringContainsString('troisieme', $text);
    }
}
