<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EnvFile;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
use YesWiki\Kernel\Service\Replication;
use YesWiki\Kernel\Service\WikiSettings;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 04: the running wiki never writes its configuration file; what an admin edits goes to its database, what an operator decides to the environment. */
#[CoversClass(ConfigurationService::class)]
#[CoversClass(WikiSettings::class)]
#[CoversClass(EnvFile::class)]
class WikiSettingsTest extends YesWikiTestCase
{
    private const SETTING = 'meta_description';

    private string $fileWas;

    private ?string $envWas;

    /** @var array<string, mixed> */
    private array $storedWas;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->fileWas = (string)file_get_contents($this->file());
        $env = $services->get(EnvFile::class)->path();
        $this->envWas = is_file($env) ? (string)file_get_contents($env) : null;
        $this->storedWas = $services->get(WikiSettings::class)->all();
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        putenv(Replication::ENV);
        putenv('CONTACT_SMTP_HOST');
        putenv('ACTIVE_EXTENSIONS');
        \YesWiki\Core\YesWikiLoader::forgetEnvFile();
        $env = $services->get(EnvFile::class)->path();
        if ($this->envWas === null) {
            @unlink($env);
        } else {
            file_put_contents($env, $this->envWas);
        }
        $db = $services->get(DbService::class);
        $db->query('DELETE FROM' . $db->prefixTable('triples') . 'WHERE resource = ?', [WikiSettings::RESOURCE]);
        $services->get(WikiSettings::class)->store($this->storedWas);
        $services->get(WikiSettings::class)->startNewRequest();
        parent::tearDown();
    }

    public function testAnAdminSettingGoesToTheDatabaseAndTheFileIsNotWritten(): void
    {
        $services = self::getWiki()->services;
        $versionWas = $services->get(WikiSettings::class)->freshVersion();

        $this->edit(self::SETTING, 'Edited from the admin');

        $this->assertSame($this->fileWas, (string)file_get_contents($this->file()), 'the file is left as it was');
        $this->assertSame('Edited from the admin', $services->get(WikiSettings::class)->all()[self::SETTING] ?? null);
        $this->assertNotSame($versionWas, $services->get(WikiSettings::class)->freshVersion(), 'the version moves, so a worker of another replica restarts');
        $this->assertSame('Edited from the admin', $this->reload()[self::SETTING] ?? null, 'and the next read sees it');
    }

    public function testASettingTakenAwayStaysAway(): void
    {
        $this->edit('wiki_status', 'hibernate');
        $this->assertSame('hibernate', $this->reload()['wiki_status'] ?? null);

        $config = $this->own();
        unset($config['wiki_status']);
        $this->assertTrue($config->write());

        $this->assertArrayNotHasKey('wiki_status', $this->reload(), 'a hibernation lifted on one replica is lifted on every one');
    }

    public function testPuttingBackWhatTheFileSaysForgetsTheSetting(): void
    {
        $plain = new \YesWiki\Kernel\Entity\ConfigurationFile($this->file());
        $plain->load();
        $fromTheFile = $plain->__get('_parameters');

        $this->edit(self::SETTING, 'Edited from the admin');
        $config = $this->own();
        if (array_key_exists(self::SETTING, $fromTheFile)) {
            $config[self::SETTING] = $fromTheFile[self::SETTING];
        } else {
            unset($config[self::SETTING]);
        }
        $this->assertTrue($config->write());

        $this->assertArrayNotHasKey(self::SETTING, self::getWiki()->services->get(WikiSettings::class)->all(), 'the database keeps only what differs from the file');
    }

    public function testAnOperatorSettingGoesToTheEnvironmentFile(): void
    {
        $services = self::getWiki()->services;

        $this->edit('contact_smtp_host', 'smtp.example.org');
        $this->edit('active_extensions', ['helloworld', 'lms']);

        $this->assertSame($this->fileWas, (string)file_get_contents($this->file()));
        $values = \YesWiki\Core\YesWikiLoader::envFileValues();
        $this->assertSame('smtp.example.org', $values['CONTACT_SMTP_HOST'] ?? null);
        $this->assertSame(['helloworld', 'lms'], EnvironmentConfiguration::apply(['active_extensions' => []], $values)['active_extensions'], 'a list goes as JSON and comes back as a list');
        $this->assertArrayNotHasKey('contact_smtp_host', $services->get(WikiSettings::class)->all(), 'and not to the database');
    }

    public function testAReplicaRefusesToChangeWhatItsOperatorDecides(): void
    {
        putenv(Replication::ENV . '=1');

        $this->expectExceptionMessage('contact_smtp_host');
        $this->edit('contact_smtp_host', 'smtp.replica.example.org');
    }

    private function edit(string $key, mixed $value): void
    {
        $config = $this->own();
        $config[$key] = $value;
        $this->assertTrue($config->write());
    }

    /** @return array<string, mixed> */
    private function reload(): array
    {
        self::getWiki()->services->get(WikiSettings::class)->startNewRequest();

        return $this->own()->__get('_parameters');
    }

    private function own(): \YesWiki\Kernel\Entity\ConfigurationFile
    {
        $config = self::getWiki()->services->get(ConfigurationService::class)->getConfiguration($this->file());
        $config->load();

        return $config;
    }

    private function file(): string
    {
        return ConfigurationFileProvider::getConfigFileFromEnv();
    }
}
