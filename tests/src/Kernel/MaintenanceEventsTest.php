<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * `maintenance.before` / `maintenance.after`: where an extension hangs its own housekeeping.
 *
 * The wiki has no cron. Maintenance runs inside whichever page view happens to arrive once
 * the interval has elapsed, which is what makes the two guarantees here worth pinning: the
 * events bracket the work (so `after` really does mean "core is done"), and a listener that
 * throws is swallowed rather than turned into a broken page for the visitor who paid for
 * the request.
 *
 * Both tests run the real `maintenance()` -- the point is the bracket around the real work
 * -- with one thing switched off: `pages_purge_time`. That is the only step here that
 * destroys anything (revisions older than a year), and a test suite must not delete a
 * developer's history as a side effect of running. `0` is how core itself disables it, so
 * nothing is stubbed and the rest of the housekeeping really does run.
 */
class MaintenanceEventsTest extends YesWikiTestCase
{
    public function testTheEventsBracketTheHousekeeping(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $dispatcher = $wiki->services->get(EventDispatcher::class);
        $restorePurge = $this->withoutRevisionPurge($wiki);

        $seen = [];
        $before = function (Event $event) use (&$seen): void {
            $seen[] = ['phase' => 'before'] + $event->getData();
        };
        $after = function (Event $event) use (&$seen): void {
            $seen[] = ['phase' => 'after'] + $event->getData();
        };
        $dispatcher->addListener('maintenance.before', $before);
        $dispatcher->addListener('maintenance.after', $after);

        try {
            $wiki->services->get(YesWikiRuntime::class)->maintenance();
        } finally {
            $dispatcher->removeListener('maintenance.before', $before);
            $dispatcher->removeListener('maintenance.after', $after);
            $restorePurge();
        }

        $this->assertSame(['before', 'after'], array_column($seen, 'phase'), 'one of each, in order');

        // what a listener is handed: enough to decide whether this run is one of its own
        foreach ($seen as $event) {
            $this->assertArrayHasKey('startedAt', $event);
            $this->assertArrayHasKey('interval', $event);
            $this->assertArrayHasKey('previousRun', $event, 'null is an answer; a missing key is not');
        }
        $this->assertArrayNotHasKey('duration', $seen[0]);
        $this->assertArrayHasKey('duration', $seen[1], 'only the end knows how long it took');
        $this->assertGreaterThanOrEqual(0, $seen[1]['duration']);
    }

    /**
     * An extension having a bad afternoon is not the visitor's problem.
     *
     * Maintenance is best-effort housekeeping on a request that asked for none of it, so a
     * listener that throws must not reach the page -- the same bargain the search-index
     * drain already takes.
     */
    public function testAListenerThatThrowsDoesNotBreakTheRequest(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $dispatcher = $wiki->services->get(EventDispatcher::class);

        $restorePurge = $this->withoutRevisionPurge($wiki);
        $ran = false;
        $explode = function (): void {
            throw new \RuntimeException('an extension having a bad afternoon');
        };
        $andYet = function () use (&$ran): void {
            $ran = true;
        };
        $dispatcher->addListener('maintenance.before', $explode);
        $dispatcher->addListener('maintenance.after', $andYet);

        try {
            $wiki->services->get(YesWikiRuntime::class)->maintenance();
        } finally {
            $dispatcher->removeListener('maintenance.before', $explode);
            $dispatcher->removeListener('maintenance.after', $andYet);
            $restorePurge();
        }

        $this->assertTrue($ran, 'the housekeeping carried on, and so did the request');
    }

    /** Turn off the one step that deletes anything, and hand back the way to restore it. */
    private function withoutRevisionPurge(YesWikiRuntime $wiki): callable
    {
        $config = $wiki->services->get(RuntimeConfig::class);
        $before = $config['pages_purge_time'] ?? null;
        $config['pages_purge_time'] = 0;

        return function () use ($config, $before): void {
            $config['pages_purge_time'] = $before;
        };
    }
}
