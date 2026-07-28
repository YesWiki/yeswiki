<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Render\Action\FaviconAction;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 06: actions and handlers stop being found by scanning a directory and become real
 * services resolved by name.
 *
 * The name is the load-bearing part. `{{favicon}}` in a page body is user data, so the
 * mechanism may change but the name may not -- which is exactly what the directory scan
 * could not guarantee once classes gained namespaces, since it derived the name from the
 * filename and YesWikiAction derived it again from get_class().
 */
class ActionRegistryTest extends YesWikiTestCase
{
    private function registry(): ActionRegistry
    {
        $registry = $this->getWiki()->services?->get(ActionRegistry::class);
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
        // Performer lowercases the name before dispatch; the registry must agree
        $registry = $this->registry();

        $this->assertTrue($registry->has('action', 'FAVICON'));
        $this->assertTrue($registry->has('action', 'Favicon'));
    }

    private function performer(): Performer
    {
        $performer = $this->getWiki()->services?->get(Performer::class);
        $this->assertInstanceOf(Performer::class, $performer);

        return $performer;
    }

    public function testAnUnconvertedActionIsNotInTheRegistryButStillRuns(): void
    {
        // the two resolution paths coexist during the migration; this is the fallback side
        $this->assertFalse($this->registry()->has('action', 'toc'));
        $this->assertContains('toc', $this->performer()->list('action'));
    }

    public function testConvertedActionsAppearInTheActionListAlongsideScannedOnes(): void
    {
        $list = $this->performer()->list('action');

        $this->assertContains('favicon', $list, 'a registered action must still be listed');
        $this->assertContains('toc', $list, 'scanned actions must still be listed');
        $this->assertSame($list, array_unique($list), 'an action must not be listed twice');
    }

    public function testTheActionStillRendersThroughWikiAction(): void
    {
        $output = $this->getWiki()->Action('favicon');

        $this->assertStringContainsString('<link rel="icon"', $output);
    }

    public function testDeclaredNamesAreLowercase(): void
    {
        // Performer lowercases on dispatch, so a capitalised declaration would be unreachable
        foreach ($this->registry()->names('action') as $name) {
            $this->assertSame(strtolower($name), $name, "action name '$name' must be lowercase");
        }
    }
}
