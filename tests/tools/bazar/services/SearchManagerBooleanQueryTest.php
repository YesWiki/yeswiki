<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The query language: AND/OR (uppercase, space-delimited) and parentheses combine fields, AND binding tighter than OR, with the legacy | and , still meaning what they did. */
class SearchManagerBooleanQueryTest extends YesWikiTestCase
{
    private const FORM_ID = '999961';

    /** @var list<string> */
    private static array $tags = [];

    public static function setUpBeforeClass(): void
    {
        $wiki = self::getWiki();
        $GLOBALS['wiki'] = $wiki;
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        if ($formManager->getOne(self::FORM_ID) !== null) {
            $formManager->delete(self::FORM_ID);
        }
        $formManager->create([
            'bn_id_nature' => self::FORM_ID,
            'bn_label_nature' => 'Boolean query test',
            'bn_template' => "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\n"
                . "texte***bf_a***A***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***\n"
                . 'texte***bf_b***B***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
        foreach ([['E1', 'toto', 'x'], ['E2', 'x', 'tata'], ['E3', 'toto', 'tata'], ['E4', 'x', 'x']] as [$title, $a, $b]) {
            self::$tags[] = $entryManager->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => $title, 'bf_a' => $a, 'bf_b' => $b])['id_fiche'];
        }
    }

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        foreach (self::$tags as $tag) {
            $entryManager->delete($tag, true);
        }
        self::$tags = [];
        $wiki->services->get(FormManager::class)->delete(self::FORM_ID);
    }

    /** @return list<string> */
    private function titles(string $query): array
    {
        $titles = array_column($this->getWiki()->services->get(EntryManager::class)->search([
            'formsIds' => [self::FORM_ID],
            'queries' => $query,
        ]), 'bf_titre');
        sort($titles);

        return $titles;
    }

    public function testOrCombinesTwoDifferentFields()
    {
        $this->assertSame(['E1', 'E2', 'E3'], $this->titles('bf_a=toto OR bf_b=tata'));
    }

    public function testAndCombinesTwoDifferentFields()
    {
        $this->assertSame(['E3'], $this->titles('bf_a=toto AND bf_b=tata'));
    }

    public function testAndBindsTighterThanOr()
    {
        $this->assertSame(['E1', 'E2', 'E3'], $this->titles('bf_a=toto OR bf_a=x AND bf_b=tata'));
    }

    public function testParenthesesOverridePrecedence()
    {
        $this->assertSame(['E2', 'E3'], $this->titles('(bf_a=toto OR bf_a=x) AND bf_b=tata'));
    }

    public function testLegacyPipeStillMeansAnd()
    {
        $this->assertSame(['E3'], $this->titles('bf_a=toto|bf_b=tata'));
    }

    public function testLegacyCommaStillOrsValuesOfOneField()
    {
        $this->assertSame(['E1', 'E2', 'E3', 'E4'], $this->titles('bf_a=toto,x'));
    }

    public function testAMalformedExpressionMatchesNothing()
    {
        $this->assertSame([], $this->titles('bf_a=toto AND (bf_b=tata'));
    }

    /**
     * The migration only rewrites the syntax, so a converted query must match exactly what the legacy one did.
     */
    public function testConvertingALegacyQueryKeepsItsMeaning()
    {
        $searchManager = $this->getWiki()->services->get(SearchManager::class);
        foreach (['bf_a=toto,x|bf_b=tata', 'bf_a=toto|bf_b=tata', 'bf_a=toto,x', 'bf_a!=toto|bf_b=tata'] as $legacy) {
            $converted = $searchManager->convertLegacyQuery($legacy);
            $this->assertNotSame($legacy, $converted, "the query should have been rewritten: $legacy");
            $this->assertSame($this->titles($legacy), $this->titles($converted), "converted query changed the result: $legacy");
        }
    }
}
