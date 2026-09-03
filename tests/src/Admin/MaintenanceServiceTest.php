<?php

namespace YesWiki\Test\Admin;

use YesWiki\Admin\Service\MaintenanceService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `maintenance_trigger`, and the sweep reporting what it did (ticket 54). */
class MaintenanceServiceTest extends YesWikiTestCase
{
    /**
     * The container, taken once.
     *
     * `getWiki()` re-pins the suite's settings every time it is called, `maintenance_trigger`
     * among them (ticket 54), so a test that asked for the wiki again would have its own setting
     * put back before it could read it.
     */
    private \Psr\Container\ContainerInterface $services;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = $this->getWiki()->services;
    }

    private function maintenance(): MaintenanceService
    {
        return $this->services->get(MaintenanceService::class);
    }

    private function config(): RuntimeConfig
    {
        return $this->services->get(RuntimeConfig::class);
    }

    /**
     * @return callable(): void the way to put the setting back
     */
    private function trigger(?string $value): callable
    {
        $config = $this->config();
        $was = $config[MaintenanceService::TRIGGER_SETTING] ?? null;
        if ($value === null) {
            unset($config[MaintenanceService::TRIGGER_SETTING]);
        } else {
            $config[MaintenanceService::TRIGGER_SETTING] = $value;
        }

        return function () use ($config, $was): void {
            $config[MaintenanceService::TRIGGER_SETTING] = $was;
        };
    }

    /** With the setting absent, the wiki behaves as it always has: a request may sweep. */
    public function testTheDefaultIsThePoorMansCron(): void
    {
        $restore = $this->trigger(null);
        try {
            $this->assertSame(MaintenanceService::TRIGGER_REQUEST, $this->maintenance()->trigger());
        } finally {
            $restore();
        }
    }

    /** The whole of what `cron` does to a request. */
    public function testCronMeansNoPageViewEverSweeps(): void
    {
        $restore = $this->trigger(MaintenanceService::TRIGGER_CRON);
        try {
            $this->assertFalse($this->maintenance()->dueOnRequest(), 'a page view swept under maintenance_trigger: cron');
        } finally {
            $restore();
        }
    }

    /** A value nobody recognises is not a third mechanism. */
    public function testAnUnknownValueFallsBackToTheDefault(): void
    {
        $restore = $this->trigger('whenever-it-feels-like-it');
        try {
            $this->assertSame(MaintenanceService::TRIGGER_REQUEST, $this->maintenance()->trigger());
        } finally {
            $restore();
        }
    }

    /** The command needs to know what happened, step by step, to be able to fail. */
    public function testASweepReportsEveryStepItRan(): void
    {
        $config = $this->config();
        $wasPurge = $config['pages_purge_time'] ?? null;
        $config['pages_purge_time'] = 0;

        try {
            $report = $this->maintenance()->sweep();
        } finally {
            $config['pages_purge_time'] = $wasPurge;
        }

        $this->assertSame(
            ['revisions', 'journal', 'recovery-keys', 'activation-keys', 'search-queue'],
            array_keys($report->steps())
        );
        $this->assertSame([], $report->failures());
        $this->assertFalse($report->hasFailures());
        $this->assertGreaterThanOrEqual(0.0, $report->duration());
    }

    /** `--force` is the only thing that gets past a sweep that has just run. */
    public function testTheIntervalLockHoldsUntilItIsForced(): void
    {
        $maintenance = $this->maintenance();

        $maintenance->claim(true);

        $this->assertFalse($maintenance->claim(), 'a second sweep ran inside the interval');
        $this->assertTrue($maintenance->claim(true), '--force could not get past the interval');
    }
}
