<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Search\Service\SearchManager;
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
        $services = self::getWiki()->services;
        $formManager = $services->get(FormManager::class);
        if ($formManager->getOne(self::FORM_ID) !== null) {
            $formManager->delete(self::FORM_ID);
        }
        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'Boolean query test',
            'template' => "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\n"
                . "texte***bf_a***A***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***\n"
                . 'texte***bf_b***B***60***255*** *** ***text***0*** *** *** * *** * *** *** *** ***',
        ]);
        $entryManager = $services->get(EntryManager::class);
        foreach ([['E1', 'toto', 'x'], ['E2', 'x', 'tata'], ['E3', 'toto', 'tata'], ['E4', 'x', 'x']] as [$title, $a, $b]) {
            $entryManager->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => $title, 'bf_a' => $a, 'bf_b' => $b, 'tag' => $title]);
            self::$tags[] = $title;
        }
    }

    public static function tearDownAfterClass(): void
    {
        $services = self::getWiki()->services;
        $entryManager = $services->get(EntryManager::class);
        foreach (self::$tags as $tag) {
            $entryManager->delete($tag, true);
        }
        self::$tags = [];
        $services->get(FormManager::class)->delete(self::FORM_ID);
    }

    /** @return list<string> */
    private function titles(string $query): array
    {
        $rows = $this->getWiki()->services->get(EntryManager::class)->search([
            'formsIds' => [self::FORM_ID],
            'queries' => $query,
        ]);
        $titles = array_values(array_filter(array_map(static fn ($r) => $r['bf_titre'] ?? null, $rows)));
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
