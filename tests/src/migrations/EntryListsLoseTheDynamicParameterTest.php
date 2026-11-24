<?php

namespace YesWiki\Test\Core\Migrations;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `dynamic` leaves the list calls, and what a Vue list did by default is written out for the server one. */
class EntryListsLoseTheDynamicParameterTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261124120000_EntryListsLoseTheDynamicParameter.php';
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function calls(): array
    {
        return [
            'a Vue accordion list stays an accordion' => [
                'Avant {{entrylist id="4" template="list" dynamic="true"}} après',
                'Avant {{entrylist id="4" template="list" entrydisplay="accordion"}} après',
            ],
            'the old accordion name in Vue was that same list' => [
                '{{bazarliste id="4" template="liste_accordeon" dynamic="1"}}',
                '{{bazarliste id="4" template="list" entrydisplay="accordion"}}',
            ],
            'a Vue card opened its entries in a modal' => [
                '{{entrylist id="1" template="card" dynamic="true" columns="3"}}',
                '{{entrylist id="1" template="card" columns="3" entrydisplay="modal"}}',
            ],
            'a stated way of opening is kept' => [
                '{{entrylist id="1" template="card.twig" entrydisplay="newtab" dynamic="true"}}',
                '{{entrylist id="1" template="card.twig" entrydisplay="newtab"}}',
            ],
            'a server list only loses the parameter' => [
                '{{entrylist id="1" template="liste_accordeon" dynamic="false"}}',
                '{{entrylist id="1" template="liste_accordeon"}}',
            ],
            'a map only loses the parameter' => [
                '{{entrymap id="2" dynamic="true" zoom="5"}}',
                '{{entrymap id="2" zoom="5"}}',
            ],
            'another action is left alone' => [
                '{{include page="X" dynamic="true"}}',
                '{{include page="X" dynamic="true"}}',
            ],
            'a list without the parameter is left alone' => [
                '{{entrylist  id="1"   template="card"}}',
                '{{entrylist  id="1"   template="card"}}',
            ],
        ];
    }

    #[DataProvider('calls')]
    public function testTheCallIsRewritten(string $before, string $after): void
    {
        $this->assertSame($after, \EntryListsLoseTheDynamicParameter::rewrite($before));
    }
}
