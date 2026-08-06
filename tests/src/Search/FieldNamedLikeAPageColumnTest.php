<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Service\FormManager;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A form whose field is called `tag` -- or `time`, or `body`, or `user`.
 *
 * The search builds one CTE that selects `p.*` and then one alias per field of the form.
 * `pages` has short, ordinary column names, so a field named after one of them declared that
 * column twice, and the two drivers disagreed about what to do:
 *
 *   MySQL   SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'tag'
 *   SQLite  accepts it, and the field's value silently replaces the page's `tag` in every row
 *
 * An error page on one, wrong rows on the other, from one query -- reported from a wiki whose
 * directory form had a `tag` field, where every list of it died. The suite runs on SQLite, so
 * it saw the harmless-looking half.
 *
 * Asserted on the generated SQL rather than on a live query, because that is the part both
 * drivers share: no column may be declared twice, whichever engine is asked.
 */
class FieldNamedLikeAPageColumnTest extends YesWikiTestCase
{
    private const FORM_ID = 8631;

    /** Every column `p.*` contributes; a field may take none of these names. */
    private const PAGES_COLUMNS = [
        'id', 'tag', 'time', 'body', 'owner', 'user', 'latest', 'type',
        'parent', 'metadata',
    ];

    public static function tearDownAfterClass(): void
    {
        $forms = self::getWiki()->services->get(FormManager::class);
        if ($forms->getOne(self::FORM_ID) !== null) {
            $forms->delete(self::FORM_ID);
        }
    }

    /**
     * A form whose fields are named after page columns. Nothing stops an author doing this:
     * the field names are theirs to choose.
     *
     * @return array<string, mixed>
     */
    private function formWithCollidingFields(): array
    {
        $forms = $this->getWiki()->services->get(FormManager::class);
        if ($forms->getOne(self::FORM_ID) !== null) {
            $forms->delete(self::FORM_ID);
        }
        $forms->create([
            'id' => self::FORM_ID,
            'label' => 'FieldNamedLikeAPageColumnTest',
            'template' => '[{"type": "texte", "name": "bf_titre", "label": "Titre"},'
                . '{"type": "texte", "name": "tag", "label": "Étiquette"},'
                . '{"type": "texte", "name": "user", "label": "Utilisateur"}]',
            'condition' => '',
        ]);

        $created = $forms->getOne(self::FORM_ID);
        $this->assertNotNull($created, 'the form should have been created');

        return $created;
    }

    /** @return list<string> the column names the SELECT of the CTE declares */
    private function declaredColumns(string $sql): array
    {
        $start = strpos($sql, 'SELECT ');
        $from = strpos($sql, ' FROM ');
        if ($start === false || $from === false) {
            $this->fail('could not read the CTE out of: ' . substr($sql, 0, 200));
        }
        $select = substr($sql, $start + 7, $from - $start - 7);

        $columns = [];
        // split on the commas that are not inside brackets -- the subselect for the type
        // triple carries its own
        $depth = 0;
        $current = '';
        foreach (str_split($select) as $character) {
            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            }
            if ($character === ',' && $depth === 0) {
                $columns[] = $current;
                $current = '';

                continue;
            }
            $current .= $character;
        }
        $columns[] = $current;

        return array_values(array_filter(array_map(
            function (string $column): string {
                $parts = preg_split('/\s+AS\s+/i', trim($column)) ?: [$column];

                return trim((string)end($parts));
            },
            $columns
        ), fn (string $column): bool => $column !== '' && $column !== 'p.*'));
    }

    public function testTheQueryDeclaresNoColumnTwice(): void
    {
        $form = $this->formWithCollidingFields();
        $params = ['formsIds' => [$form['id']]];

        $sql = $this->getWiki()->services->get(SearchManager::class)->prepareSearchRequest($params);
        $this->assertNotSame('', $sql, 'the form should produce a query at all');

        $declared = $this->declaredColumns($sql);
        $collisions = array_intersect(array_map('strtolower', $declared), self::PAGES_COLUMNS);

        $this->assertSame([], array_values($collisions), 'no field may shadow a column of `pages`');
        $this->assertSame(
            array_unique($declared),
            $declared,
            'and nothing may be declared twice: MySQL refuses the whole query'
        );
    }

    /**
     * The renamed column is the one the conditions use, or the filter would silently read the
     * page's tag instead of the field.
     */
    public function testAConditionOnThatFieldUsesTheSameName(): void
    {
        $form = $this->formWithCollidingFields();
        $params = ['formsIds' => [$form['id']], 'queries' => ['tag' => 'quelque-chose']];

        $sql = $this->getWiki()->services->get(SearchManager::class)->prepareSearchRequest($params);

        $this->assertStringContainsString('yw_field__tag', $sql, 'the condition names the renamed column');
        $this->assertMatchesRegularExpression(
            '/yw_field__tag[^,]*(LIKE|REGEXP|~|=)/i',
            $sql,
            'and it is the field being compared, not the page tag'
        );
    }

    /** A field with an ordinary name is left exactly as it was. */
    public function testAnOrdinaryFieldNameIsUntouched(): void
    {
        $form = $this->formWithCollidingFields();
        $params = ['formsIds' => [$form['id']], 'queries' => ['bf_titre' => 'quelque-chose']];

        $sql = $this->getWiki()->services->get(SearchManager::class)->prepareSearchRequest($params);

        $this->assertStringContainsString('bf_titre', $sql);
        $this->assertStringNotContainsString('yw_field__bf_titre', $sql);
    }
}
