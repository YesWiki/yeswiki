<?php

namespace YesWiki\Test\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Service\FormManager;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A form field named something that is not an identifier.
 *
 * Field names are user data: the form designer stores whatever the webmaster typed and nothing
 * anywhere validates it. SearchManager then puts that name into the SQL it generates in five
 * positions -- a column alias, a bare column reference in a WHERE, a CTE name with `_multiple`
 * glued on, a value inside `s.champ = '...'`, and a JSON path inside a string literal.
 *
 * Before this was fixed the same name was emitted two different ways sixty lines apart: raw at
 * the `single`-mode keyword condition, and wrapped in `escape()` at the excluded-keyword one.
 * Neither is right for an identifier -- `escape()` is a string-literal escaper, so it doubles
 * quotes and does nothing about spaces, parens or backticks -- and the pair of them is evidence
 * nobody knew which was. Note it takes webmaster rights to name a field, so this was never a
 * visitor-reachable hole; it is an unvalidated path from stored data into SQL text, and the
 * duplicate-column bug FieldNamedLikeAPageColumnTest covers is the same path breaking by
 * accident rather than on purpose.
 *
 * The fix constrains the name once, where every reference already funnels through, so a name
 * that is already an identifier is untouched -- which is every field name in every real wiki.
 */
class FieldNameIsNotSqlTest extends YesWikiTestCase
{
    private const FORM_ID = 8632;

    public static function tearDownAfterClass(): void
    {
        $forms = self::getWiki()->services->get(FormManager::class);
        if ($forms->getOne(self::FORM_ID) !== null) {
            $forms->delete(self::FORM_ID);
        }
    }

    /** @return list<string> */
    private static function safeIdentifierFor(string $name): array
    {
        $method = new \ReflectionMethod(SearchManager::class, 'asSafeIdentifier');

        return [(string)$method->invoke(null, $name)];
    }

    /**
     * Names that already are identifiers, which must come back byte-identical: the alias is the
     * key callers read a field's value back by, so changing one would silently break every
     * consumer of a search result.
     *
     * @return array<string, array{string}>
     */
    public static function ordinaryNames(): array
    {
        return [
            'a bazar field' => ['bf_titre'],
            'with digits' => ['bf_champ2'],
            'leading underscore' => ['_internal'],
            'a page column (prefixing happens later)' => ['tag'],
            'long but valid' => [str_repeat('a', 80)],
        ];
    }

    #[DataProvider('ordinaryNames')]
    public function testAnOrdinaryFieldNameIsNotRewritten(string $name): void
    {
        $this->assertSame([$name], self::safeIdentifierFor($name));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostileNames(): array
    {
        return [
            'a single quote' => ["bf_a'b"],
            'a backtick' => ['bf_a`b'],
            'a double quote' => ['bf_a"b'],
            'a space' => ['bf a'],
            'a comment marker' => ['bf_a-- '],
            'a semicolon' => ['bf_a; DROP TABLE yeswiki_pages'],
            'parentheses' => ['bf_a()'],
            'a whole condition' => ["x' OR '1'='1"],
            'starts with a digit' => ['1field'],
            'accented' => ['bf_téléphone'],
            'empty' => [''],
            'nothing but punctuation' => ['---'],
        ];
    }

    #[DataProvider('hostileNames')]
    public function testAHostileFieldNameBecomesAPlainIdentifier(string $name): void
    {
        [$safe] = self::safeIdentifierFor($name);

        $this->assertMatchesRegularExpression(
            '/^[A-Za-z_][A-Za-z0-9_]*$/',
            $safe,
            'whatever the author typed, what reaches the SQL must be an identifier'
        );
        // MySQL caps an identifier at 64 characters, and the CTE appends `_multiple`
        $this->assertLessThanOrEqual(54, strlen($safe));
    }

    /**
     * Two different odd names must not collapse onto one column, or one field's filter would
     * read the other field's values.
     */
    public function testTwoDifferentHostileNamesStayDifferent(): void
    {
        [$first] = self::safeIdentifierFor("bf_a'b");
        [$second] = self::safeIdentifierFor('bf_a"b');

        $this->assertNotSame($first, $second);
    }

    /**
     * The end-to-end half: a real form with a hostile field name, and the SQL that comes out.
     *
     * Asserted on the generated statement rather than by running it, for the reason
     * FieldNamedLikeAPageColumnTest gives -- the generated text is the part every driver shares.
     */
    public function testTheGeneratedSqlCarriesNoneOfTheName(): void
    {
        $forms = $this->getWiki()->services->get(FormManager::class);
        if ($forms->getOne(self::FORM_ID) !== null) {
            $forms->delete(self::FORM_ID);
        }
        $forms->create([
            'id' => self::FORM_ID,
            'label' => 'FieldNameIsNotSqlTest',
            'template' => '[{"type": "texte", "name": "bf_titre", "label": "Titre"},'
                . '{"type": "texte", "name": "bf_x\' OR 1=1 -- ", "label": "Hostile"}]',
            'condition' => '',
        ]);
        $created = $forms->getOne(self::FORM_ID);
        $this->assertNotNull($created, 'the form should have been created');

        // prepareSearchRequest() takes its params by reference, so they need a variable
        $params = ['formsIds' => [$created['id']]];
        $sql = $this->getWiki()->services->get(SearchManager::class)->prepareSearchRequest($params);
        $this->assertNotSame('', $sql, 'the form should produce a query at all');

        // the JSON path is a string literal and legitimately holds the real name, escaped;
        // what must not appear is the name in an identifier position, where a bare `'` would
        // end the statement early
        $withoutLiterals = (string)preg_replace("/'(?:[^']|'')*'/", "''", $sql);

        $this->assertStringNotContainsString('OR 1=1', $withoutLiterals, 'the name must not reach SQL outside a literal');
        $this->assertStringNotContainsString('--', $withoutLiterals, 'nor may it comment out the rest of the statement');
        $this->assertSame(
            substr_count($sql, "'") % 2,
            0,
            'the quotes in the generated statement must balance'
        );
    }
}
