<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `maintenance_trigger` leaves the configuration, and the admin is told once what to do in its place. */
class MaintenanceTriggerBecomesAQueueModeTest extends YesWikiTestCase
{
    private string $file = '';
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
        putenv('YESWIKI_CONFIG_FILE=' . $this->file);
    }

    protected function tearDown(): void
    {
        putenv($this->previousConfigFile === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->previousConfigFile);
        if ($this->readOnlyDir !== '') {
            @chmod($this->readOnlyDir, 0o755);
        }
        @chmod($this->file, 0o644);
        @unlink($this->file);
        if ($this->readOnlyDir !== '') {
            @rmdir($this->readOnlyDir);
        }
        parent::tearDown();
    }

    public function testTheSettingIsRemovedAndTheMessageIsGivenOnce(): void
    {
        $this->configure(['wakka_name' => 'test', 'maintenance_trigger' => 'cron']);

        $first = $this->migrate();
        $second = $this->migrate();

        $written = $this->read();
        $this->assertArrayNotHasKey('maintenance_trigger', $written);
        $this->assertArrayNotHasKey('queue_consumer', $written, 'no mode is chosen in the admin\'s place');
        $this->assertSame('test', $written['wakka_name']);
        $this->assertCount(1, $first->notes());
        $this->assertStringContainsString("'cron'", $first->notes()[0]);
        $this->assertStringContainsString('crontab', $first->notes()[0]);
        $this->assertStringContainsString('worker', $first->notes()[0]);
        $this->assertStringContainsString('api', $first->notes()[0]);
        $this->assertSame([], $second->notes(), 'the setting is gone: nothing more to say');
    }

    public function testAConfigurationWithoutItIsLeftAloneAndSaysNothing(): void
    {
        $this->configure(['wakka_name' => 'test', 'queue_consumer' => 'worker']);
        $before = (string)file_get_contents($this->file);

        $migration = $this->migrate();

        $this->assertSame([], $migration->notes());
        $this->assertSame($before, (string)file_get_contents($this->file));
    }

    public function testItFailsLoudlyWhenTheConfigurationCannotBeWritten(): void
    {
        $this->readOnlyDir = sys_get_temp_dir() . '/yw-trigger-ro-' . bin2hex(random_bytes(4));
        mkdir($this->readOnlyDir);
        $this->file = $this->readOnlyDir . '/yeswiki.config.php';
        putenv('YESWIKI_CONFIG_FILE=' . $this->file);
        $this->configure(['maintenance_trigger' => 'request']);
        chmod($this->file, 0o444);
        chmod($this->readOnlyDir, 0o555);
        if (is_writable($this->file)) {
            $this->markTestSkipped('this user can write a read-only file');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maintenance_trigger is still there');
        $this->migrate();
    }

    /** @param array<string, mixed> $values */
    private function configure(array $values): void
    {
        file_put_contents($this->file, "<?php\n\n\$yeswikiConfig = " . var_export($values, true) . ";\n");
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        $config = $this->getWiki()->services->get(ConfigurationService::class)->getConfiguration($this->file);
        $config->load();

        return $config->__get('_parameters');
    }

    private function migrate(): \MaintenanceTriggerBecomesAQueueMode
    {
        $services = $this->getWiki()->services;
        $migration = new \MaintenanceTriggerBecomesAQueueMode();
        $migration->setServices($services);
        $migration->setDbService($services->get(DbService::class));
        $migration->setParams($services->get(ParameterBagInterface::class));
        $migration->run();

        return $migration;
    }
}
