<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Kernel\Service\DbService;
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
        self::backdate($services->get(DbService::class));
    }

    /** E1..E3 carry their own dates, E4 is an older entry that never stored created_at nor updated_at. */
    private static function backdate(DbService $db): void
    {
        $dates = [
            'E1' => ['2020-01-10 10:00:00', '2022-05-01 10:00:00', 'QueryOwnerA'],
            'E2' => ['2021-06-15 10:00:00', '2023-05-01 10:00:00', 'QueryOwnerB'],
            'E3' => ['2023-03-03 10:00:00', '2024-05-01 10:00:00', 'QueryOwnerA'],
        ];
        $pages = $db->prefixTable('pages');
        $time = $db->quoteIdentifier('time');
        foreach (self::$tags as $tag) {
            $row = $db->loadSingle("SELECT body FROM {$pages} WHERE tag = ? AND latest = 'Y'", [$tag]);
            $body = json_decode((string)($row['body'] ?? '{}'), true);
            if (isset($dates[$tag])) {
                [$created, $updated, $owner] = $dates[$tag];
                $body['created_at'] = $created;
                $body['updated_at'] = $updated;
            } else {
                unset($body['created_at'], $body['updated_at']);
                $owner = 'QueryOwnerB';
                $db->query("UPDATE {$pages} SET {$time} = ? WHERE tag = ?", ['2019-05-05 10:00:00', $tag]);
            }
            $db->query(
                "UPDATE {$pages} SET body = ?, owner = ? WHERE tag = ? AND latest = 'Y'",
                [json_encode($body), $owner, $tag]
            );
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

    public function testOrCombinesTwoDifferentFields(): void
    {
        $this->assertSame(['E1', 'E2', 'E3'], $this->titles('bf_a=toto OR bf_b=tata'));
    }

    public function testAndCombinesTwoDifferentFields(): void
    {
        $this->assertSame(['E3'], $this->titles('bf_a=toto AND bf_b=tata'));
    }

    public function testAndBindsTighterThanOr(): void
    {
        $this->assertSame(['E1', 'E2', 'E3'], $this->titles('bf_a=toto OR bf_a=x AND bf_b=tata'));
    }

    public function testParenthesesOverridePrecedence(): void
    {
        $this->assertSame(['E2', 'E3'], $this->titles('(bf_a=toto OR bf_a=x) AND bf_b=tata'));
    }

    public function testLegacyPipeStillMeansAnd(): void
    {
        $this->assertSame(['E3'], $this->titles('bf_a=toto|bf_b=tata'));
    }

    public function testLegacyCommaStillOrsValuesOfOneField(): void
    {
        $this->assertSame(['E1', 'E2', 'E3', 'E4'], $this->titles('bf_a=toto,x'));
    }

    public function testAMalformedExpressionMatchesNothing(): void
    {
        $this->assertSame([], $this->titles('bf_a=toto AND (bf_b=tata'));
    }

    public function testConvertingALegacyQueryKeepsItsMeaning(): void
    {
        $searchManager = $this->getWiki()->services->get(SearchManager::class);
        foreach (['bf_a=toto,x|bf_b=tata', 'bf_a=toto|bf_b=tata', 'bf_a=toto,x', 'bf_a!=toto|bf_b=tata'] as $legacy) {
            $converted = $searchManager->convertLegacyQuery($legacy);
            $this->assertNotSame($legacy, $converted, "the query should have been rewritten: $legacy");
            $this->assertSame($this->titles($legacy), $this->titles($converted), "converted query changed the result: $legacy");
        }
    }

    public function testCreationDateIsQueryable(): void
    {
        $this->assertSame(['E2', 'E3'], $this->titles('created_at>2021-01-01'));
        $this->assertSame(['E1', 'E4'], $this->titles('created_at<2021-01-01'));
    }

    public function testCreationDateFallsBackToTheFirstRevisionTime(): void
    {
        $this->assertSame(['E4'], $this->titles('created_at<2020-01-01'));
    }

    public function testUpdateDateIsQueryable(): void
    {
        $this->assertSame(['E2', 'E3'], $this->titles('updated_at>=2023-01-01'));
        $this->assertSame(['E1', 'E4'], $this->titles('updated_at<2023-01-01'));
    }

    public function testDatesCombineWithOtherFields(): void
    {
        $this->assertSame(['E3'], $this->titles('created_at>2021-01-01 AND bf_a=toto'));
    }

    public function testOwnerFormIdAndStatusAreQueryable(): void
    {
        $this->assertSame(['E1', 'E3'], $this->titles('owner=QueryOwnerA'));
        $this->assertSame(['E1', 'E2', 'E3', 'E4'], $this->titles('form_id=' . self::FORM_ID));
        $this->assertSame([], $this->titles('form_id!=' . self::FORM_ID));
        $this->assertSame(['E1', 'E2', 'E3', 'E4'], $this->titles('status=1'));
    }
}
