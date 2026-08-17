<?php

namespace YesWiki\Test\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Service\FormManager;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A form field named something that is not an identifier. */
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

    /**
     * @return list<string>
     */
    private static function safeIdentifierFor(string $name): array
    {
        $method = new \ReflectionMethod(SearchManager::class, 'asSafeIdentifier');

        return [(string)$method->invoke(null, $name)];
    }

    /**
     * Names that already are identifiers, which must come back byte-identical: the alias is the key callers read a field's value back by, so changing one would silently break every consumer of a search result.
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

        $this->assertLessThanOrEqual(54, strlen($safe));
    }

    /**
     * Two different odd names must not collapse onto one column, or one field's filter would read the other field's values.
     */
    public function testTwoDifferentHostileNamesStayDifferent(): void
    {
        [$first] = self::safeIdentifierFor("bf_a'b");
        [$second] = self::safeIdentifierFor('bf_a"b');

        $this->assertNotSame($first, $second);
    }

    /** The end-to-end half: a real form with a hostile field name, and the SQL that comes out. */
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

        $params = ['formsIds' => [$created['id']]];
        $sql = $this->getWiki()->services->get(SearchManager::class)->prepareSearchRequest($params)->sql;
        $this->assertNotSame('', $sql, 'the form should produce a query at all');

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
