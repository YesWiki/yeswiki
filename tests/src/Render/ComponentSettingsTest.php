<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Component\ComponentSettings;
use YesWiki\Render\Component\QueryTree;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The component rail on the server: a tag read into the panel's state, edited as the panel posts it, and written back. */
class ComponentSettingsTest extends YesWikiTestCase
{
    private ?string $actorWas = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actorWas = $this->getWiki()->services->get(\YesWiki\Identity\Service\AuthenticationService::class)->getLoggedUserName() ?: null;
        self::actAs(self::adminName());
    }

    protected function tearDown(): void
    {
        self::actAs($this->actorWas);
        parent::tearDown();
    }

    private function settings(): ComponentSettings
    {
        return $this->getWiki()->services->get(ComponentSettings::class);
    }

    private function roundTrip(string $tag): string
    {
        $found = $this->settings()->fromTag($tag);
        $this->assertNotNull($found, $tag);

        return $this->settings()->code($found['component'], $found['state'], true);
    }

    public function testATagReadAndWrittenAgainIsTheSameTag(): void
    {
        $this->assertSame('{{button link="https://yeswiki.net" text="Hello"}}', $this->roundTrip('button text="Hello" link="https://yeswiki.net"'));
        $this->assertSame('{{entrylist id="2" template="card" displayfields="title=bf_nom"}}', $this->roundTrip('{{entrylist id="2" template="card" displayfields="title=bf_nom"}}'));
        $this->assertSame('{{section bgcolor="#ff0000" class="full-width cover"}}', $this->roundTrip('section bgcolor="#ff0000" class="full-width cover"'));
    }

    public function testAParameterNoSettingDrawsIsKept(): void
    {
        $this->assertStringContainsString('madeup="kept"', $this->roundTrip('button text="Hello" madeup="kept"'));
    }

    public function testTheSourceSettingChoosesTheTag(): void
    {
        $found = $this->settings()->fromTag('entrylist id="2" template="card"');
        $this->assertNotNull($found);
        $post = ['v' => ['source' => 'syndication', 'id' => ['2'], 'url' => 'https://example.org/feed']];
        $code = $this->settings()->code($found['component'], $this->settings()->fromPost($found['component'], $post), true);

        $this->assertStringStartsWith('{{syndication ', $code);
        $this->assertStringNotContainsString('id="2"', $code, 'the form picker belongs to the entrylist source and is hidden for a feed');
        $this->assertStringContainsString('template="card"', $code, 'what the presentation pins stays');
    }

    public function testAValueLeftAtItsDefaultIsNotWritten(): void
    {
        $found = $this->settings()->fromTag('entrylist id="2" template="card"');
        $this->assertNotNull($found);
        $post = ['v' => ['source' => 'entrylist', 'id' => ['1', '2'], 'nb' => '3', 'order' => 'asc']];
        $code = $this->settings()->code($found['component'], $this->settings()->fromPost($found['component'], $post), true);

        $this->assertSame('{{entrylist id="1,2" template="card" nb="3"}}', $code);
    }

    public function testRowsAreAddedRemovedAndWrittenAsTheirParameters(): void
    {
        $found = $this->settings()->fromTag('entrylist id="2" template="card" groups="bf_type" titles="Type"');
        $this->assertNotNull($found);
        $component = $found['component'];
        $rows = [['field' => 'bf_type', 'title' => 'Type', 'icon' => ''], ['field' => 'bf_ville', 'title' => '', 'icon' => '']];
        $base = ['v' => ['source' => 'entrylist', 'id' => ['2']], 's' => ['facets' => ['rows' => $rows]]];

        $code = $this->settings()->code($component, $this->settings()->fromPost($component, $base), true);
        $this->assertStringContainsString('groups="bf_type,bf_ville"', $code);
        $this->assertStringContainsString('titles="Type,"', $code, 'a title stays next to its field');

        $added = $this->settings()->fromPost($component, $base + ['op' => 'add-row:facets']);
        $this->assertCount(3, $added['special']['facets']);
        $removed = $this->settings()->fromPost($component, $base + ['op' => 'remove-row:facets:0']);
        $this->assertSame('bf_ville', $removed['special']['facets'][0]['field']);
    }

    public function testAHiddenSettingIsNotWritten(): void
    {
        $found = $this->settings()->fromTag('entrylist id="2" template="card"');
        $this->assertNotNull($found);
        $post = ['v' => ['source' => 'entrylist', 'id' => ['2'], 'filterposition' => 'top']];
        $code = $this->settings()->code($found['component'], $this->settings()->fromPost($found['component'], $post), true);

        $this->assertStringNotContainsString('filterposition', $code, 'where the facets go is asked only once there are facets');
    }

    public function testANewWrapperIsWrittenWithItsContent(): void
    {
        $component = $this->settings()->component('section');
        $this->assertNotNull($component);
        $state = $this->settings()->fresh('section');
        $this->assertNotNull($state);

        $this->assertMatchesRegularExpression('/^\{\{section[^}]*\}\}\n.+\n\{\{end elem="section"\}\}$/s', $this->settings()->code($component, $state, false));
        $this->assertStringEndsWith('}}', $this->settings()->code($component, $state, true));
        $this->assertStringNotContainsString('{{end', $this->settings()->code($component, $state, true), 'an existing wrapper rewrites its opening tag only');
    }

    public function testTheQueryTreeReadsTheOldSyntaxAndWritesTheNewOne(): void
    {
        $tree = QueryTree::read('bf_type=1,2|bf_ville!=Lyon');
        $this->assertNotNull($tree);
        $this->assertSame('(bf_type=1 OR bf_type=2) AND bf_ville!=Lyon', QueryTree::write($tree));

        $tree = QueryTree::add($tree, [], QueryTree::CONDITION);
        $tree['children'][2] = ['field' => 'bf_titre', 'operator' => '=', 'value' => 'Bordeaux'];
        $this->assertStringEndsWith(' AND bf_titre=Bordeaux', QueryTree::write($tree));
        $this->assertSame('bf_ville!=Lyon AND bf_titre=Bordeaux', QueryTree::write(QueryTree::remove($tree, ['0'])));

        $this->assertNull(QueryTree::read('a=1 AND (b=2'), 'a query the tree cannot hold is edited as text');
    }
}
