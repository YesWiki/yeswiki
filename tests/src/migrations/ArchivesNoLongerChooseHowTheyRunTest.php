<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `archive.call_archive_async` leaves the configuration: backups and restores go through the queue, whose mode decides how they run. */
class ArchivesNoLongerChooseHowTheyRunTest extends YesWikiTestCase
{
    private string $file = '';
    private string|false $previousConfigFile = false;

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261023130000_ArchivesNoLongerChooseHowTheyRun.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = sys_get_temp_dir() . '/yw-archive-async-config-' . bin2hex(random_bytes(4)) . '.php';
        $this->previousConfigFile = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE=' . $this->file);
    }

    protected function tearDown(): void
    {
        putenv($this->previousConfigFile === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->previousConfigFile);
        @unlink($this->file);
        parent::tearDown();
    }

    public function testTheSettingIsRemovedAndTheOthersOfTheSectionStay(): void
    {
        $this->configure(['archive' => ['call_archive_async' => false, 'max_nb_files' => 4, 'preupdate_backup_activated' => true]]);

        $migration = $this->migrate();

        $this->assertSame(['max_nb_files' => 4, 'preupdate_backup_activated' => true], $this->read()['archive']);
        $this->assertCount(1, $migration->notes(), 'the admin is told what was removed, with the value it had');
        $this->assertStringContainsString('false', $migration->notes()[0]);
    }

    public function testRunningItAgainChangesNothing(): void
    {
        $this->configure(['archive' => ['call_archive_async' => true, 'max_nb_files' => 4]]);
        $this->migrate();
        $after = (string)file_get_contents($this->file);

        $second = $this->migrate();

        $this->assertSame($after, (string)file_get_contents($this->file));
        $this->assertSame([], $second->notes());
    }

    public function testAWikiWithoutTheSectionIsLeftAlone(): void
    {
        $this->configure(['wakka_name' => 'test']);
        $before = (string)file_get_contents($this->file);

        $migration = $this->migrate();

        $this->assertSame($before, (string)file_get_contents($this->file));
        $this->assertSame([], $migration->notes());
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

    private function migrate(): \ArchivesNoLongerChooseHowTheyRun
    {
        $services = $this->getWiki()->services;
        $migration = new \ArchivesNoLongerChooseHowTheyRun();
        $migration->setServices($services);
        $migration->setDbService($services->get(DbService::class));
        $migration->setParams($services->get(ParameterBagInterface::class));
        $migration->run();

        return $migration;
    }
}
