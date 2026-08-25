<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Render\Action\FaviconAction;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 06: actions and handlers stop being found by scanning a directory and become real services resolved by name.
 */
class ActionRegistryTest extends YesWikiTestCase
{
    private function registry(): ActionRegistry
    {
        $registry = $this->getWiki()->services->get(ActionRegistry::class);
        $this->assertInstanceOf(ActionRegistry::class, $registry);

        return $registry;
    }

    public function testAConvertedActionIsResolvableByItsDeclaredName(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->has('action', FaviconAction::performableName()));
        $this->assertInstanceOf(FaviconAction::class, $registry->get('action', 'favicon'));
    }

    public function testLookupIsCaseInsensitiveLikeTheWikiSyntax(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->has('action', 'FAVICON'));
        $this->assertTrue($registry->has('action', 'Favicon'));
    }

    private function performer(): Performer
    {
        $performer = $this->getWiki()->services->get(Performer::class);
        $this->assertInstanceOf(Performer::class, $performer);

        return $performer;
    }

    public function testUnknownNamesAreNotResolved(): void
    {
        $registry = $this->registry();

        $this->assertFalse($registry->has('action', 'no-such-action'));
        $this->assertNull($registry->get('action', 'no-such-action'));
    }

    public function testHookFilesAreNotRegisteredAsActions(): void
    {
        foreach ($this->registry()->names('action') as $name) {
            $this->assertStringNotContainsString('__', $name, "'$name' looks like a hook, not an action");
        }
    }

    public function testConvertedActionsAppearInTheActionListAlongsideScannedOnes(): void
    {
        $list = $this->performer()->list('action');

        $this->assertContains('favicon', $list, 'a registered action must still be listed');
        $this->assertContains('toc', $list, 'every converted action must still be listed');
        $this->assertSame($list, array_unique($list), 'an action must not be listed twice');
    }

    public function testTheActionStillRendersThroughWikiAction(): void
    {
        $output = $this->getWiki()->services->get(ActionRunner::class)->action('favicon');

        $this->assertStringContainsString('<link rel="icon"', $output);
    }

    public function testDeclaredNamesAreLowercase(): void
    {
        foreach ($this->registry()->names('action') as $name) {
            $this->assertSame(strtolower($name), $name, "action name '$name' must be lowercase");
        }
    }
}
