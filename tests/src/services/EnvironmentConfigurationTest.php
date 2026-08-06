<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\EnvironmentConfiguration;

#[CoversMethod(EnvironmentConfiguration::class, 'apply')]
#[CoversMethod(EnvironmentConfiguration::class, 'knownEnvNames')]
class EnvironmentConfigurationTest extends TestCase
{
    /** @var string[] env names set via putenv during a test, cleared in tearDown */
    private array $putEnvNames = [];

    protected function tearDown(): void
    {
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
        // matched case-insensitively against the existing camelCase key, cast to bool
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
        // db_port was absent from the config but is a known key
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

    public function testRealEnvIgnoredForUnknownVariables(): void
    {
        // OS variables like HOST or LANG must not leak into the config: only
        // file-authored entries may use the generic uppercased-key rule
        $this->setRealEnv('FAVORITES_ACTIVATED', 'false');

        $config = EnvironmentConfiguration::apply(['favorites_activated' => true], []);

        $this->assertTrue($config['favorites_activated']);
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

    public function testEnvironmentOnlyVariablesNeverBecomeConfig(): void
    {
        $config = EnvironmentConfiguration::apply([], [
            'YESWIKI_CONFIG_FILE' => 'other.config.php',
            'ADMIN_NAME' => 'WikiAdmin',
            'ADMIN_PASSWORD' => 'secret',
            'ADMIN_EMAIL' => 'me@example.com',
        ]);

        // Named keys, not assertSame([], $config): apply() also consults the *real*
        // environment for every known name, by design -- that is the documented "real values
        // win over private/.env" rule a few lines below. Asserting the result is empty
        // therefore also asserts that the machine running the tests exports none of them,
        // which is not this test's claim and is not true on CI: the workflow exports
        // DB_USER=root for the installer, so `db_user => 'root'` appeared here and this was
        // the one failure in the run that had nothing to do with the code under test.
        foreach (['yeswiki_config_file', 'admin_name', 'admin_password', 'admin_email'] as $key) {
            $this->assertArrayNotHasKey($key, $config);
        }
    }
}
