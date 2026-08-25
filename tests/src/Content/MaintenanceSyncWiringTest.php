<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Import\Service\MaintenanceSyncSubscriber;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The automatic import is wired to the wiki's housekeeping, and only to it. */
class MaintenanceSyncWiringTest extends YesWikiTestCase
{
    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testTheSchedulerListensToMaintenance(YesWikiRuntime $wiki): void
    {
        $listeners = $wiki->services->get(EventDispatcher::class)->getListeners('maintenance.after');

        $subscribed = array_filter($listeners, function ($listener) {
            return is_array($listener) && $listener[0] instanceof MaintenanceSyncSubscriber;
        });

        $this->assertCount(1, $subscribed, 'nothing imports data sources when the wiki does its housekeeping');
    }

    #[Depends('testWikiExisting')]
    public function testHousekeepingImportsNothingWhenNoSourceAsksForIt(YesWikiRuntime $wiki): void
    {
        $errors = $wiki->services->get(EventDispatcher::class)->yesWikiDispatch('maintenance.after', [
            'startedAt' => time(),
            'interval' => 1800,
            'previousRun' => null,
        ]);

        $this->assertSame([], $errors);
    }
}
