<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\EnvironmentConfiguration;

#[CoversMethod(EnvironmentConfiguration::class, 'apply')]
#[CoversMethod(EnvironmentConfiguration::class, 'knownEnvNames')]
class EnvironmentConfigurationTest extends TestCase
{
    /**
     * @var string[] env names set via putenv during a test, cleared in tearDown
     */
    private array $putEnvNames = [];

    /** @var array<string, string|false> the wiki's own private/.env, kept out of what these tests apply */
    private array $wikisEnvWas = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (\defined('YESWIKI_INSTANCE_DIR')) {
            foreach (array_keys(\YesWiki\Core\YesWikiLoader::envFileValues()) as $name) {
                $this->wikisEnvWas[$name] = getenv($name);
                putenv($name);
            }
            \YesWiki\Core\YesWikiLoader::useEnvFileValues([]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->wikisEnvWas as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
        if (\defined('YESWIKI_INSTANCE_DIR')) {
            \YesWiki\Core\YesWikiLoader::forgetEnvFile();
        }
        foreach ($this->putEnvNames as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
        $this->putEnvNames = [];
        parent::tearDown();
    }

    private function setRealEnv(string $name, string $value): void
    {
        putenv("$name=$value");
        $this->putEnvNames[] = $name;
    }

    public function testFileValuesOverrideAnyConfigKey(): void
    {
        $config = EnvironmentConfiguration::apply(
            ['base_url' => 'http://old/?', 'htmlPurifierActivated' => false, 'favorites_activated' => true],
            ['BASE_URL' => 'http://new/?', 'HTMLPURIFIERACTIVATED' => 'true', 'FAVORITES_ACTIVATED' => 'false']
        );

        $this->assertSame('http://new/?', $config['base_url']);

        $this->assertTrue($config['htmlPurifierActivated']);
        $this->assertFalse($config['favorites_activated']);
    }

    public function testFileValuesCanCreateNewKeys(): void
    {
        $config = EnvironmentConfiguration::apply([], ['SOME_CUSTOM_PARAM' => 'value']);

        $this->assertSame('value', $config['some_custom_param']);
    }

    public function testKnownKeysCanBeCreatedWhenAbsent(): void
    {
        $config = EnvironmentConfiguration::apply(
            ['yeswiki_name' => 'Old name', 'db_driver' => 'mysql'],
            ['YESWIKI_NAME' => 'My wiki', 'DB_DRIVER' => 'sqlite', 'DB_PORT' => '3307']
        );

        $this->assertSame('My wiki', $config['yeswiki_name']);
        $this->assertSame('sqlite', $config['db_driver']);

        $this->assertSame('3307', $config['db_port']);
    }

    public function testRealEnvWinsOverFileForKnownVariables(): void
    {
        $this->setRealEnv('DB_PASSWORD', 'from-real-env');

        $config = EnvironmentConfiguration::apply(
            ['db_password' => 'from-config'],
            ['DB_PASSWORD' => 'from-file']
        );

        $this->assertSame('from-real-env', $config['db_password']);
    }

    /** An environment variable wins over the database for any setting it names (replicas 20), but not the variables every process carries. */
    public function testRealEnvWinsForAnySettingItNames(): void
    {
        $this->setRealEnv('FAVORITES_ACTIVATED', 'false');

        $config = EnvironmentConfiguration::apply(['favorites_activated' => true, 'path' => 'kept'], []);

        $this->assertFalse($config['favorites_activated']);
        $this->assertSame('kept', $config['path'], 'PATH names no setting');
    }

    public function testValuesAreCastToTheReplacedType(): void
    {
        $config = EnvironmentConfiguration::apply(
            ['revisionscount' => 30, 'allow_raw_html' => false, 'debug' => false],
            ['REVISIONSCOUNT' => '50', 'ALLOW_RAW_HTML' => 'true', 'DEBUG' => 'true']
        );

        $this->assertSame(50, $config['revisionscount']);
        $this->assertTrue($config['allow_raw_html']);
        $this->assertTrue($config['debug']);
    }

    public function testArrayValuedKeysAreNeverOverridden(): void
    {
        $config = EnvironmentConfiguration::apply(
            ['allowed_methods_in_iframe' => ['iframe', 'render']],
            ['ALLOWED_METHODS_IN_IFRAME' => 'everything']
        );

        $this->assertSame(['iframe', 'render'], $config['allowed_methods_in_iframe']);
    }

    /** Replicas 04 puts the operator's settings in the environment, so a container's own variables must reach the configuration, a list included. */
    public function testEveryOperatorSettingComesFromTheRealEnvironment(): void
    {
        $this->setRealEnv('ACTIVE_EXTENSIONS', '["lms","ferme"]');
        $this->setRealEnv('MAX_FILE_SIZE', '64M');
        $this->setRealEnv('YESWIKI_RELEASE', '9.9.9');

        $config = EnvironmentConfiguration::apply(['active_extensions' => [], 'yeswiki_release' => '5.1.0'], []);

        $this->assertSame(['lms', 'ferme'], $config['active_extensions']);
        $this->assertSame('64M', $config['max_file_size']);
        $this->assertSame('5.1.0', $config['yeswiki_release'], 'the release comes from composer.json, never from the environment');
        $missing = array_diff(\YesWiki\Kernel\Service\SettingKinds::OPERATOR, array_values(EnvironmentConfiguration::knownEnvNames()), ['yeswiki_version', 'yeswiki_release']);
        $this->assertSame([], array_values($missing), 'every operator setting can be set by the environment');
    }

    public function testEnvironmentOnlyVariablesNeverBecomeConfig(): void
    {
        $config = EnvironmentConfiguration::apply([], [
            'YESWIKI_CONFIG_FILE' => 'other.config.php',
            'ADMIN_NAME' => 'WikiAdmin',
            'ADMIN_PASSWORD' => 'secret',
            'ADMIN_EMAIL' => 'me@example.com',
        ]);

        foreach (['yeswiki_config_file', 'admin_name', 'admin_password', 'admin_email'] as $key) {
            $this->assertArrayNotHasKey($key, $config);
        }
    }

    public function testTheQueueVariableFromTheRealEnvironmentOverridesTheConfigKey(): void
    {
        $this->setRealEnv('YESWIKI_QUEUE_CONSUMER', 'worker');

        $config = EnvironmentConfiguration::apply(['queue_consumer' => 'api'], []);

        $this->assertSame('worker', $config['queue_consumer']);
        $this->assertArrayNotHasKey('yeswiki_queue_consumer', $config);
    }

    public function testTheQueueVariableFromDotEnvOverridesTheConfigKey(): void
    {
        $config = EnvironmentConfiguration::apply(['queue_consumer' => ''], ['YESWIKI_QUEUE_CONSUMER' => 'api']);

        $this->assertSame('api', $config['queue_consumer']);
        $this->assertArrayNotHasKey('yeswiki_queue_consumer', $config);
    }
}
