<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ServiceLocator;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `maintenance_trigger` leaves the configuration, and the queue's mode stays the admin's to choose; a wiki whose crontab ran `core:maintenance` is told to drop it. */
class MaintenanceTriggerBecomesAQueueModeTest extends YesWikiTestCase
{
    private string $file = '';
    private string $root = '';
    private string $readOnlyDir = '';
    private string|false $previousConfigFile = false;

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261023140000_MaintenanceTriggerBecomesAQueueMode.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = sys_get_temp_dir() . '/yw-trigger-config-' . bin2hex(random_bytes(4)) . '.php';
        $this->previousConfigFile = getenv('YESWIKI_CONFIG_FILE');
    }

    protected function tearDown(): void
    {
        putenv($this->previousConfigFile === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->previousConfigFile);
        if ($this->readOnlyDir !== '') {
            @chmod($this->readOnlyDir, 0o755);
            @chmod($this->file, 0o644);
        }
        @unlink($this->file);
        if ($this->readOnlyDir !== '') {
            @rmdir($this->readOnlyDir);
        }
        if ($this->root !== '') {
            @unlink($this->root . '/cache/maintenance.lock');
            @rmdir($this->root . '/cache');
            @rmdir($this->root);
        }
        parent::tearDown();
    }

    /** `./yeswicli core:maintenance` is gone with it: the wiki falls back to the default mode, and nothing picks another one for the admin. */
    public function testCronIsRemovedAndTheCrontabIsToldToGo(): void
    {
        $configuration = $this->configuration(['wakka_name' => 'test', 'maintenance_trigger' => ' Cron ']);

        $this->assertSame(\MaintenanceTriggerBecomesAQueueMode::CRON_REMOVED, \MaintenanceTriggerBecomesAQueueMode::retire($configuration));
        $this->assertTrue($configuration->write());

        $written = $this->reread();
        $this->assertNull($written['queue_consumer'], 'no mode is chosen in the admin\'s place');
        $this->assertNull($written['maintenance_trigger']);
        $this->assertSame('test', $written['wakka_name']);

        $removed = (string)\MaintenanceTriggerBecomesAQueueMode::advice(\MaintenanceTriggerBecomesAQueueMode::REMOVED, "'cron'", null);
        $said = (string)\MaintenanceTriggerBecomesAQueueMode::advice(\MaintenanceTriggerBecomesAQueueMode::CRON_REMOVED, "'cron'", null);
        $this->assertStringContainsString('core:maintenance', $said, 'the command whose crontab line must go is named');
        $this->assertTrue(str_starts_with($said, $removed), 'what is said of any removal comes first');
        $this->assertGreaterThan(strlen($removed), strlen($said), 'and the crontab is told to go on top of it');
    }

    /** private/.env is not the configuration, and is not rewritten: its line is said to be dead, and the crontab with it. */
    public function testATriggerInDotEnvIsSaidToDoNothingAnyMore(): void
    {
        $said = (string)\MaintenanceTriggerBecomesAQueueMode::advice(null, '', 'cron');
        $other = (string)\MaintenanceTriggerBecomesAQueueMode::advice(null, '', 'request');

        $this->assertStringContainsString('MAINTENANCE_TRIGGER', $said);
        $this->assertStringContainsString('private/.env', $said);
        $this->assertStringContainsString("'cron'", $said, 'the value found is quoted back');
        $this->assertStringContainsString("'request'", $other);
        $this->assertStringContainsString('core:maintenance', $said);
        $this->assertStringNotContainsString('core:maintenance', $other, 'only a cron value points at a crontab');
        $this->assertNull(\MaintenanceTriggerBecomesAQueueMode::advice(null, '', null), 'nothing to say about a wiki that had neither');
        $this->assertStringNotContainsString('core:maintenance', (string)\MaintenanceTriggerBecomesAQueueMode::advice(\MaintenanceTriggerBecomesAQueueMode::REMOVED, "'request'", null));
    }

    public function testRequestIsSimplyRemoved(): void
    {
        $configuration = $this->configuration(['wakka_name' => 'test', 'maintenance_trigger' => 'request']);

        $this->assertSame(\MaintenanceTriggerBecomesAQueueMode::REMOVED, \MaintenanceTriggerBecomesAQueueMode::retire($configuration));
        $this->assertTrue($configuration->write());

        $written = $this->reread();
        $this->assertNull($written['maintenance_trigger']);
        $this->assertNull($written['queue_consumer'], 'the default mode, request, is what a page view did already');
    }

    public function testAnExplicitModeIsKept(): void
    {
        $inTheConfiguration = $this->configuration(['maintenance_trigger' => 'cron', 'queue_consumer' => 'worker']);
        $this->assertSame(\MaintenanceTriggerBecomesAQueueMode::CRON_REMOVED, \MaintenanceTriggerBecomesAQueueMode::retire($inTheConfiguration));
        $this->assertSame('worker', $inTheConfiguration['queue_consumer']);
        $this->assertNull($inTheConfiguration['maintenance_trigger']);

        $mistyped = $this->configuration(['maintenance_trigger' => 'cron', 'queue_consumer' => 'cronjob']);
        \MaintenanceTriggerBecomesAQueueMode::retire($mistyped);
        $this->assertSame('cronjob', $mistyped['queue_consumer'], 'a choice, even a mistyped one, is the admin\'s to correct');
    }

    public function testAConfigurationWithoutItIsLeftAlone(): void
    {
        $configuration = $this->configuration(['wakka_name' => 'test']);

        $this->assertNull(\MaintenanceTriggerBecomesAQueueMode::retire($configuration));
        $this->assertNull($configuration['queue_consumer']);
    }

    /** What `yeswicli migrate` does: the retired lock goes, the setting leaves the file the wiki reads, and the admin is told. */
    public function testRunRetiresTheLockAndTheSettingAndSaysSo(): void
    {
        $migration = $this->migration(['wakka_name' => 'test', 'maintenance_trigger' => 'cron'], withLock: true);

        $migration->run();

        $this->assertFileDoesNotExist($this->root . '/cache/maintenance.lock');
        $written = $this->reread();
        $this->assertNull($written['maintenance_trigger']);
        $this->assertSame('test', $written['wakka_name']);
        $this->assertCount(1, $migration->notes());
        $this->assertStringContainsString("'cron'", $migration->notes()[0]);
        $this->assertStringContainsString('core:maintenance', $migration->notes()[0]);
    }

    public function testRunOnAWikiWithoutTheSettingSaysNothingAndWritesNothing(): void
    {
        $migration = $this->migration(['wakka_name' => 'test']);
        $before = (string)file_get_contents($this->file);

        $migration->run();

        $this->assertSame([], $migration->notes());
        $this->assertSame($before, (string)file_get_contents($this->file));
    }

    public function testRunFailsLoudlyWhenTheConfigurationCannotBeWritten(): void
    {
        $this->readOnlyDir = sys_get_temp_dir() . '/yw-trigger-ro-' . bin2hex(random_bytes(4));
        mkdir($this->readOnlyDir);
        $this->file = $this->readOnlyDir . '/yeswiki.config.php';
        $migration = $this->migration(['maintenance_trigger' => 'request']);
        chmod($this->file, 0o444);
        chmod($this->readOnlyDir, 0o555);
        if (is_writable($this->file)) {
            $this->markTestSkipped('this user can write a read-only file');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maintenance_trigger is still there');
        $migration->run();
    }

    /** @param array<string, mixed> $values */
    private function configuration(array $values): ConfigurationFile
    {
        file_put_contents($this->file, "<?php\n\n\$yeswikiConfig = " . var_export($values, true) . ";\n");

        return $this->reread();
    }

    /**
     * The migration, pointed at a configuration file and an instance folder of its own.
     *
     * @param array<string, mixed> $values
     */
    private function migration(array $values, bool $withLock = false): \MaintenanceTriggerBecomesAQueueMode
    {
        $this->configuration($values);
        $this->root = sys_get_temp_dir() . '/yw-trigger-root-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/cache', 0o777, true);
        if ($withLock) {
            file_put_contents($this->root . '/cache/maintenance.lock', '');
        }
        putenv('YESWIKI_CONFIG_FILE=' . $this->file);
        $services = $this->getWiki()->services;
        $migration = new \MaintenanceTriggerBecomesAQueueMode();
        $migration->setServices(new ServiceLocator([
            Storage::class => fn (): Storage => Storage::rootedAt($this->root),
            ConfigurationService::class => static fn (): ConfigurationService => $services->get(ConfigurationService::class),
        ]));

        return $migration;
    }

    private function reread(): ConfigurationFile
    {
        $configuration = $this->getWiki()->services->get(ConfigurationService::class)->getConfiguration($this->file);
        $configuration->load();

        return $configuration;
    }
}
