<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Action\EntryListAction;
use YesWiki\Content\Service\TemplateDataFactory;
use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * There is one list action, and the presentations are its templates (ticket 49).
 *
 * `{{entrymap}}` used to be an action that formatted a few arguments and called
 * `{{entrylist}}`, which called it back with a class name in the arguments to stop the
 * recursion. What is left of it is a deprecated spelling and a preparer keyed on the template.
 */
class ListTemplateAliasesTest extends YesWikiTestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function aliasProvider(): array
    {
        return [
            'entrymap' => ['entrymap', 'template'],
            'calendar' => ['calendar', 'template'],
            'entrytable' => ['entrytable', 'template'],
            'entryuserpage' => ['entryuserpage', 'filteruserasowner'],
        ];
    }

    #[DataProvider('aliasProvider')]
    public function testAnAliasResolvesToTheOneListAction(string $alias, string $expectedDefault): void
    {
        $registry = self::getWiki()->services->get(ActionRegistry::class);

        [$name, $defaults] = $registry->resolve('action', $alias);

        $this->assertSame('entrylist', $name, "{$alias} must resolve to the action it aliases");
        $this->assertArrayHasKey($expectedDefault, $defaults, "{$alias} carries the argument its name implies");
        $this->assertInstanceOf(EntryListAction::class, $registry->get('action', $name));
    }

    /** A name nobody aliased comes back untouched, so every caller can resolve unconditionally. */
    public function testANameThatIsNotAnAliasIsLeftAlone(): void
    {
        $this->assertSame(['entrylist', []], self::getWiki()->services->get(ActionRegistry::class)->resolve('action', 'entrylist'));
    }

    /**
     * The ACL is the reason resolution happens before anything else: an alias that kept a
     * permission of its own would be a permission nobody knew they were granting.
     */
    public function testNoAliasIsRegisteredAsAnActionOfItsOwn(): void
    {
        $names = self::getWiki()->services->get(ActionRegistry::class)->names('action');

        foreach (array_keys(EntryListAction::performableAliases()) as $alias) {
            $this->assertNotContains($alias, $names, "{$alias} is a spelling of entrylist, not an action with its own ACL");
        }
    }

    /** The map's leaflet vocabulary, which `renderMap()` reads and nothing else produced. */
    public function testTheMapTemplateIsPreparedWhoeverAsksForIt(): void
    {
        $prepared = self::getWiki()->services->get(TemplateDataFactory::class)->prepare('map', ['template' => 'map']);

        foreach (['iconSize', 'iconAnchor', 'popupAnchor', 'smallmarker', 'provider', 'geolocationfield'] as $key) {
            $this->assertArrayHasKey($key, $prepared, "renderMap() reads {$key} and would emit broken JS without it");
        }
        $this->assertSame('bf_geolocation', $prepared['geolocationfield']);
    }

    /** `map.twig` and `map` are the same template written two ways, and both must prepare. */
    public function testTheTemplateExtensionIsNotPartOfTheName(): void
    {
        $factory = self::getWiki()->services->get(TemplateDataFactory::class);

        $this->assertTrue($factory->knows('map'));
        $this->assertTrue($factory->knows('map.twig'));
        $this->assertTrue($factory->knows('calendar'));
        $this->assertFalse($factory->knows('liste_accordeon'), 'a template that needs nothing extra has no preparer');
    }

    /**
     * Composition is the whole reason preparers replaced the ping-pong: `map-and-table` needs
     * both, and used to get them by re-entering EntryListAction three times.
     */
    public function testMapAndTableIsPreparedByBothOfThem(): void
    {
        $prepared = self::getWiki()->services->get(TemplateDataFactory::class)->prepare(
            'map-and-table',
            ['template' => 'map-and-table', 'dynamic' => true, 'id' => '1']
        );

        $this->assertArrayHasKey('iconSize', $prepared, 'the map preparer did not run');
        $this->assertArrayHasKey('columnfieldsids', $prepared, 'the table preparer did not run');
    }

    /**
     * `{{entrytable}}` draws the bazar table.
     *
     * It used to draw the wiki's default list template, because the action of that name
     * formatted arguments and passed no template of its own. `tableau` is the bazar table and
     * is not the shared `table` Presentation, which is why the alias names it exactly.
     */
    public function testEntrytableAsksForTheBazarTable(): void
    {
        [, $defaults] = self::getWiki()->services->get(ActionRegistry::class)->resolve('action', 'entrytable');

        $this->assertSame('tableau', $defaults['template']);
    }

    /** The server-rendered `table` is a shared Presentation and does not pay for the form read. */
    public function testTheServerRenderedTableIsNotGivenColumns(): void
    {
        $prepared = self::getWiki()->services->get(TemplateDataFactory::class)->prepare(
            'table',
            ['template' => 'table', 'dynamic' => false, 'id' => '1']
        );

        $this->assertArrayNotHasKey('columnfieldsids', $prepared);
    }
}
