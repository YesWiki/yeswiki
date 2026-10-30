<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `maintenance_trigger` leaves the configuration; a wiki whose crontab did the housekeeping keeps it, as the `command` mode.
 *
 * The real environment is emptied of the mode and the binary's marker for each test, and
 * private/.env is replaced by what the test says, so the developer's own settings take no part.
 */
class MaintenanceTriggerBecomesAQueueModeTest extends YesWikiTestCase
{
    private string $file = '';

    /** @var array<string, string|false> */
    private array $environment = [];

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261023140000_MaintenanceTriggerBecomesAQueueMode.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = sys_get_temp_dir() . '/yw-trigger-config-' . bin2hex(random_bytes(4)) . '.php';
        foreach ([QueueSettings::ENV_MODE, QueueSettings::ENV_BINARY] as $name) {
            $this->environment[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        foreach ($this->environment as $name => $was) {
            putenv($was === false ? $name : "{$name}={$was}");
        }
        parent::tearDown();
    }

    public function testCronBecomesCommand(): void
    {
        $configuration = $this->configuration(['wakka_name' => 'test', 'maintenance_trigger' => ' Cron ']);

        $this->assertSame(\MaintenanceTriggerBecomesAQueueMode::BECOMES_COMMAND, \MaintenanceTriggerBecomesAQueueMode::retire($configuration, static fn (): array => []));
        $this->assertTrue($configuration->write());

        $written = $this->reread();
        $this->assertSame('command', $written['queue_consumer'], 'its crontab exists: it now drains the queue');
        $this->assertNull($written['maintenance_trigger']);
        $this->assertSame('test', $written['wakka_name']);
    }

    /** The old crontab ran the sweep hourly, as the docs said; every background job now waits for it, so the admin is told how often to run it. */
    public function testAWikiMovedToCommandIsToldToDrainEveryFewMinutes(): void
    {
        $said = (string)\MaintenanceTriggerBecomesAQueueMode::advice(\MaintenanceTriggerBecomesAQueueMode::BECOMES_COMMAND, "'cron'", null);

        $this->assertStringContainsString("queue_consumer 'command'", $said);
        $this->assertStringContainsString('every few minutes', $said);
        $this->assertStringContainsString('messenger:drain', $said);
    }

    /** private/.env is not the configuration, and is not rewritten: its line is said to be dead, and how to keep a crontab's work. */
    public function testATriggerInDotEnvIsSaidToDoNothingAnyMore(): void
    {
        $said = (string)\MaintenanceTriggerBecomesAQueueMode::advice(null, '', 'cron');

        $this->assertStringContainsString('MAINTENANCE_TRIGGER', $said);
        $this->assertStringContainsString('private/.env', $said);
        $this->assertStringContainsString('YESWIKI_QUEUE_CONSUMER=command', $said);
        $this->assertStringContainsString('every few minutes', $said);
        $this->assertNull(\MaintenanceTriggerBecomesAQueueMode::advice(null, '', null), 'nothing to say about a wiki that had neither');
        $this->assertStringNotContainsString('every few minutes', (string)\MaintenanceTriggerBecomesAQueueMode::advice(\MaintenanceTriggerBecomesAQueueMode::REMOVED, "'request'", null));
    }

    public function testRequestIsSimplyRemoved(): void
    {
        $configuration = $this->configuration(['wakka_name' => 'test', 'maintenance_trigger' => 'request']);

        $this->assertSame(\MaintenanceTriggerBecomesAQueueMode::REMOVED, \MaintenanceTriggerBecomesAQueueMode::retire($configuration, static fn (): array => []));
        $this->assertTrue($configuration->write());

        $written = $this->reread();
        $this->assertNull($written['maintenance_trigger']);
        $this->assertNull($written['queue_consumer'], 'the default mode, request, is what a page view did already');
    }

    public function testAnExplicitModeIsKept(): void
    {
        $inTheConfiguration = $this->configuration(['maintenance_trigger' => 'cron', 'queue_consumer' => 'worker']);
        $this->assertSame(\MaintenanceTriggerBecomesAQueueMode::REMOVED, \MaintenanceTriggerBecomesAQueueMode::retire($inTheConfiguration, static fn (): array => []));
        $this->assertSame('worker', $inTheConfiguration['queue_consumer']);
        $this->assertNull($inTheConfiguration['maintenance_trigger']);

        $mistyped = $this->configuration(['maintenance_trigger' => 'cron', 'queue_consumer' => 'cronjob']);
        \MaintenanceTriggerBecomesAQueueMode::retire($mistyped, static fn (): array => []);
        $this->assertSame('cronjob', $mistyped['queue_consumer'], 'a choice, even a mistyped one, is the admin\'s to correct');

        $inDotEnv = $this->configuration(['maintenance_trigger' => 'cron']);
        \MaintenanceTriggerBecomesAQueueMode::retire($inDotEnv, static fn (): array => [QueueSettings::ENV_MODE => 'api']);
        $this->assertNull($inDotEnv['queue_consumer'], 'private/.env already chose');

        putenv(QueueSettings::ENV_MODE . '=request-spawn');
        $inTheEnvironment = $this->configuration(['maintenance_trigger' => 'cron']);
        \MaintenanceTriggerBecomesAQueueMode::retire($inTheEnvironment, static fn (): array => []);
        $this->assertNull($inTheEnvironment['queue_consumer'], 'the server\'s environment already chose');
        $this->assertNull($inTheEnvironment['maintenance_trigger']);
    }

    public function testUnderTheBinaryCronIsSimplyRemoved(): void
    {
        putenv(QueueSettings::ENV_BINARY . '=1');
        $configuration = $this->configuration(['maintenance_trigger' => 'cron']);

        $this->assertSame(\MaintenanceTriggerBecomesAQueueMode::REMOVED, \MaintenanceTriggerBecomesAQueueMode::retire($configuration, static fn (): array => []));
        $this->assertNull($configuration['maintenance_trigger']);
        $this->assertNull($configuration['queue_consumer'], 'the binary consumes by itself: a crontab calling the drain would only find an empty queue');
    }

    public function testAConfigurationWithoutItIsLeftAlone(): void
    {
        $configuration = $this->configuration(['wakka_name' => 'test']);

        $this->assertNull(\MaintenanceTriggerBecomesAQueueMode::retire($configuration, static fn (): array => []));
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
