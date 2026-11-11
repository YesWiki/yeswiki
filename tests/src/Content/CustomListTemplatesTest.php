<?php

namespace YesWiki\Test\Content;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Action\EntryListAction;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Render\Service\TemplateOverrides;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 18: the actions builder offers the list templates a webmaster added under `core/`, which is where `@core/<template>` renders them from. */
class CustomListTemplatesTest extends YesWikiTestCase
{
    private const SAVED = ['core/zz-probe-list.twig', 'core/fiche-99.twig', 'core/accordeon.twig', 'core/zz-probe-folder/deeper.twig'];

    private function overrides(): TemplateOverrides
    {
        return self::getWiki()->services->get(TemplateOverrides::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::SAVED as $path) {
            $this->overrides()->save($path, '<ul>{% for entry in entries %}<li>{{ entry.bf_titre }}</li>{% endfor %}</ul>');
        }
    }

    protected function tearDown(): void
    {
        foreach (self::SAVED as $path) {
            $this->overrides()->delete($path);
        }
        parent::tearDown();
    }

    /** @return list<string> the templates the builder's own-template components write */
    private function offered(): array
    {
        $action = new EntryListAction();
        $action->setServices(self::getWiki()->services);
        $action->setParams(self::getWiki()->services->get(ParameterBagInterface::class));
        $offered = [];
        foreach ((new \ReflectionMethod(EntryListAction::class, 'customTemplateComponents'))->invoke($action) as $component) {
            $offered[] = $component->pins()['template'] ?? '';
        }

        return $offered;
    }

    public function testATopLevelCoreTemplateOfItsOwnIsOfferedAndRenders(): void
    {
        $this->assertContains('zz-probe-list.twig', $this->offered());
        $this->assertTrue(self::getWiki()->services->get(TemplateEngine::class)->hasTemplate('@core/zz-probe-list.twig'), 'what is offered is what renders');
    }

    public function testEntryTemplatesShippedOverridesAndDeeperFilesAreNotLists(): void
    {
        $offered = $this->offered();

        $this->assertNotContains('fiche-99.twig', $offered);
        $this->assertNotContains('accordeon.twig', $offered, 'an override of a shipped template is not a template of its own');
        $this->assertNotContains('deeper.twig', $offered);
    }
}
