<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The facets filter the list, on the server (ticket 37). */
class FacetFilteringTest extends YesWikiTestCase
{
    private ?Request $previousRequest = null;

    protected function tearDown(): void
    {
        if ($this->previousRequest !== null) {
            $this->getWiki()->services->get(CurrentRequest::class)->replace($this->previousRequest);
            $this->previousRequest = null;
        }
        parent::tearDown();
    }

    private function facets(string $queryString): BazarListService
    {
        $currentRequest = $this->getWiki()->services->get(CurrentRequest::class);
        $this->previousRequest ??= $currentRequest->get();
        $currentRequest->replace(Request::create('/?PageDeTest&' . $queryString));

        return $this->getWiki()->services->get(BazarListService::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function entries(): array
    {
        return [
            ['tag' => 'Un', 'bf_type' => '1', 'bf_ville' => 'Nantes'],
            ['tag' => 'Deux', 'bf_type' => '2,3', 'bf_ville' => 'Nantes'],
            ['tag' => 'Trois', 'bf_type' => '3', 'bf_ville' => 'Rennes'],
            ['tag' => 'Quatre', 'bf_type' => '', 'bf_ville' => 'Rennes'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return list<string>
     */
    private static function tags(array $entries): array
    {
        return array_column($entries, 'tag');
    }

    /** What the form posts, which is the spelling everything else is built on. */
    public function testTheCheckedBoxesComeFromTheUrl(): void
    {
        $service = $this->facets('facet%5Bbf_type%5D%5B%5D=1&facet%5Bbf_type%5D%5B%5D=3');

        $this->assertSame(['bf_type' => ['1', '3']], $service->checkedFacets());
    }

    /** ...and the one the tag input writes, which holds a facet's values in one parameter. */
    public function testOneParameterMayHoldSeveralValues(): void
    {
        $service = $this->facets('facet%5Bbf_type%5D=1%2C3');

        $this->assertSame(['bf_type' => ['1', '3']], $service->checkedFacets());
    }

    /** ...and the spelling every link already out there uses. */
    public function testTheOlderUrlSpellingStillMeansTheSame(): void
    {
        $service = $this->facets('facet=bf_type%3D1%2C3%7Cbf_ville%3DNantes');

        $this->assertSame(
            ['bf_type' => ['1', '3'], 'bf_ville' => ['Nantes']],
            $service->checkedFacets()
        );
    }

    public function testNothingCheckedLeavesTheListAlone(): void
    {
        $service = $this->facets('');

        $this->assertSame(
            ['Un', 'Deux', 'Trois', 'Quatre'],
            self::tags($service->filterEntriesOnFacets(self::entries()))
        );
    }

    /**
     * Two values of one box are an OR -- "type 1 or type 3" -- and that is not a detail: it is what makes a box a set of alternatives rather than a radio button.
     */
    public function testValuesOfOneBoxAreAlternatives(): void
    {
        $service = $this->facets('');

        $this->assertSame(
            ['Un', 'Deux', 'Trois'],
            self::tags($service->filterEntriesOnFacets(self::entries(), ['bf_type' => ['1', '3']]))
        );
    }

    /** ...while two boxes narrow each other. */
    public function testTwoBoxesNarrowEachOther(): void
    {
        $service = $this->facets('');

        $this->assertSame(
            ['Trois'],
            self::tags($service->filterEntriesOnFacets(
                self::entries(),
                ['bf_type' => ['1', '3'], 'bf_ville' => ['Rennes']]
            ))
        );
    }

    /**
     * A checkbox field holds its values comma-separated, and `2,3` means both of them -- not one value spelled "2,3".
     */
    public function testAValueInsideAMultipleChoiceFieldCounts(): void
    {
        $service = $this->facets('');

        $this->assertSame(
            ['Deux'],
            self::tags($service->filterEntriesOnFacets(self::entries(), ['bf_type' => ['2']]))
        );
        $this->assertSame(
            ['Deux', 'Trois'],
            self::tags($service->filterEntriesOnFacets(self::entries(), ['bf_type' => ['3']]))
        );
    }

    /**
     * A facet over a *linked* entry's field is not a key of the entry at all: it only exists as one of the data attributes `EntryExtraFieldsService::appendHtmlData()` wrote, which is where the browser used to read it from.
     */
    public function testAFacetOverALinkedEntrysFieldReadsItsDataAttribute(): void
    {
        $service = $this->facets('');
        $entries = [
            ['tag' => 'Liee', 'html_data' => 'data-bf_acteurs_-_ActeurUn_-_bf_type="7" '],
            ['tag' => 'Autre', 'html_data' => 'data-bf_acteurs_-_ActeurDeux_-_bf_type="8" '],
        ];

        $this->assertSame(
            ['Liee'],
            self::tags($service->filterEntriesOnFacets(
                $entries,
                ['bf_acteurs_-_ActeurUn_-_bf_type' => ['7']]
            ))
        );
    }
}
