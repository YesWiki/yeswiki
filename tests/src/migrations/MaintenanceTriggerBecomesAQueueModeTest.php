<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `maintenance_trigger` leaves the configuration, and the queue's mode stays the admin's to choose; a wiki whose crontab ran `core:maintenance` is told to drop it. */
class MaintenanceTriggerBecomesAQueueModeTest extends YesWikiTestCase
{
    private string $file = '';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261023140000_MaintenanceTriggerBecomesAQueueMode.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = sys_get_temp_dir() . '/yw-trigger-config-' . bin2hex(random_bytes(4)) . '.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
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

        $said = (string)\MaintenanceTriggerBecomesAQueueMode::advice(\MaintenanceTriggerBecomesAQueueMode::CRON_REMOVED, "'cron'", null);
        $this->assertStringContainsString('core:maintenance', $said);
        $this->assertStringContainsString('crontab', $said);
        $this->assertStringContainsString('worker', $said);
        $this->assertStringContainsString('api', $said);
        $this->assertStringNotContainsString('command', str_replace('crontab', '', $said));
    }

    /** private/.env is not the configuration, and is not rewritten: its line is said to be dead, and the crontab with it. */
    public function testATriggerInDotEnvIsSaidToDoNothingAnyMore(): void
    {
        $said = (string)\MaintenanceTriggerBecomesAQueueMode::advice(null, '', 'cron');

        $this->assertStringContainsString('MAINTENANCE_TRIGGER', $said);
        $this->assertStringContainsString('private/.env', $said);
        $this->assertStringContainsString('core:maintenance', $said);
        $this->assertStringContainsString('worker', $said);
        $this->assertStringNotContainsString('YESWIKI_QUEUE_CONSUMER=command', $said);
        $this->assertNull(\MaintenanceTriggerBecomesAQueueMode::advice(null, '', null), 'nothing to say about a wiki that had neither');
        $this->assertStringNotContainsString('crontab', (string)\MaintenanceTriggerBecomesAQueueMode::advice(\MaintenanceTriggerBecomesAQueueMode::REMOVED, "'request'", null));
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

    /** @param array<string, mixed> $values */
    private function configuration(array $values): ConfigurationFile
    {
        file_put_contents($this->file, "<?php\n\n\$yeswikiConfig = " . var_export($values, true) . ";\n");

        return $this->reread();
    }

    private function reread(): ConfigurationFile
    {
        $configuration = $this->getWiki()->services->get(ConfigurationService::class)->getConfiguration($this->file);
        $configuration->load();

        return $configuration;
    }
}
