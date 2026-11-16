<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\DoryphoreLeftovers;
use YesWiki\Kernel\Service\WikiSettings;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The conversion on a wiki as it really runs: its own `yeswiki.config.php`, so what the migration writes goes to the database
 * (replicas 04), and the Doryphore keys left in the file -- a client secret among them -- are taken out of it.
 * The file and the stored settings are put back as they were after each test.
 */
class LoginExtensionsBecomeProvidersInTheDatabaseTest extends YesWikiTestCase
{
    private const SECRET = 'sso-client-secret-value';

    private string $file = '';

    private string $fileWas = '';

    /** @var array<string, mixed> */
    private array $storedWas = [];

    private string $instance = '';

    private string|false $configFileWas = false;

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261111100000_LoginExtensionsBecomeProviders.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->configFileWas = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE');
        $this->file = YESWIKI_INSTANCE_DIR . '/yeswiki.config.php';
        if (!is_file($this->file)) {
            $this->markTestSkipped('this wiki runs on another configuration file, so its settings are not stored in the database');
        }
        $this->fileWas = (string)file_get_contents($this->file);
        $settings = self::getWiki()->services->get(WikiSettings::class);
        $settings->startNewRequest();
        $this->storedWas = $settings->all();
        $this->instance = sys_get_temp_dir() . '/login-extensions-db-' . bin2hex(random_bytes(4));
        mkdir($this->instance . '/' . DoryphoreLeftovers::ASIDE . '/tools/loginsso', 0755, true);
    }

    protected function tearDown(): void
    {
        if ($this->fileWas !== '') {
            $this->replaceTheFile($this->fileWas);
        }
        $services = self::getWiki()->services;
        $db = $services->get(DbService::class);
        $db->query('DELETE FROM' . $db->prefixTable('triples') . 'WHERE resource = ?', [WikiSettings::RESOURCE]);
        $services->get(WikiSettings::class)->store($this->storedWas);
        $services->get(WikiSettings::class)->startNewRequest();
        exec('rm -rf ' . escapeshellarg($this->instance));
        putenv($this->configFileWas === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $this->configFileWas);
        parent::tearDown();
    }

    /**
     * The file as it is on disk, with nothing the database says laid over it.
     *
     * @return array<array-key, mixed>
     */
    private function onDisk(): array
    {
        $configuration = new ConfigurationFile($this->file);
        $configuration->load();

        return $configuration->__get('_parameters');
    }

    /** Adds Doryphore's loginsso keys to the wiki's own file, the way an upgraded wiki has them. */
    private function giveTheFileLoginssoKeys(): void
    {
        $values = $this->onDisk() + ['sso_config' => ['providers' => [[
            'auth_type' => 'oauth2',
            'auth_options' => [
                'clientId' => 'the-client',
                'clientSecret' => self::SECRET,
                'urlAuthorize' => 'https://s/auth',
                'urlAccessToken' => 'https://s/token',
                'urlResourceOwnerDetails' => 'https://s/userinfo',
            ],
            'id_sso_field' => 'sub',
            'button_style' => ['button_label' => 'My Auth Server'],
        ]]]];
        $this->replaceTheFile("<?php\n\n\$wakkaConfig = " . var_export($values, true) . ";\n");
    }

    /** A new file renamed over the old one, as the wiki writes it: a rewrite in place can leave a network mount serving the old size. */
    private function replaceTheFile(string $content): void
    {
        $next = $this->file . '.test-' . bin2hex(random_bytes(4));
        file_put_contents($next, $content);
        chmod($next, 0600);
        rename($next, $this->file);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->file, true);
        }
    }

    private function migration(): \LoginExtensionsBecomeProviders
    {
        $migration = new \LoginExtensionsBecomeProviders();
        $migration->setServices(self::getWiki()->services);
        $migration->setDbService(self::getWiki()->services->get(DbService::class));

        return $migration;
    }

    private function migrate(\LoginExtensionsBecomeProviders $migration): void
    {
        // no account is walked: only the configuration is under test here
        $migration->convertWiki($this->instance, $this->file, new \DateTimeImmutable('2026-11-11 10:00:00'), []);
        self::getWiki()->services->get(WikiSettings::class)->startNewRequest();
    }

    public function testTheProvidersAreStoredAndTheOldKeysLeaveTheFile(): void
    {
        $this->giveTheFileLoginssoKeys();
        $baseUrl = $this->onDisk()['base_url'] ?? null;

        $this->migrate($this->migration());

        $stored = self::getWiki()->services->get(WikiSettings::class)->all();
        $this->assertSame('oidc', $stored['sso_providers']['my-auth-server']['type'] ?? null, 'the providers are the wiki\'s settings, in its database');
        $onDisk = $this->onDisk();
        $this->assertArrayNotHasKey('sso_config', $onDisk, 'the old section leaves the file');
        $this->assertStringNotContainsString(self::SECRET, (string)file_get_contents($this->file), 'and the client secret with it');
        $this->assertSame($baseUrl, $onDisk['base_url'] ?? null, 'the rest of the file is kept');
    }

    public function testAFileThatCannotBeWrittenIsNamedWithTheKeysToDelete(): void
    {
        $this->giveTheFileLoginssoKeys();
        $migration = new class extends \LoginExtensionsBecomeProviders {
            protected function removeFromTheFile(string $file, array $keys): bool
            {
                return false;
            }
        };
        $migration->setServices(self::getWiki()->services);
        $migration->setDbService(self::getWiki()->services->get(DbService::class));

        $this->migrate($migration);

        $stored = self::getWiki()->services->get(WikiSettings::class)->all();
        $this->assertSame('oidc', $stored['sso_providers']['my-auth-server']['type'] ?? null, 'the conversion holds: the file is not where it lives');
        $said = implode("\n", $migration->notes());
        $this->assertStringContainsString('sso_config', $said, 'the operator is told which keys to delete');
        $this->assertStringNotContainsString(self::SECRET, $said);
    }
}
