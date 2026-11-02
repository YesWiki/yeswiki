<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\Queue\QueueSettings;

/** Which consumption mode the wiki runs in, and why: the real environment, then private/.env, then the configuration, then the default. */
class QueueSettingsTest extends TestCase
{
    /**
     * @var array<string, string|false> variables touched by a test, as they were before it
     */
    private array $savedEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $before) {
            putenv($before === false ? $name : "$name=$before");
        }
        $this->savedEnv = [];
        parent::tearDown();
    }

    private function setRealEnv(string $name, string $value): void
    {
        $this->savedEnv[$name] ??= getenv($name);
        putenv("$name=$value");
    }

    private function unsetRealEnv(string $name): void
    {
        $this->savedEnv[$name] ??= getenv($name);
        putenv($name);
    }

    /**
     * @param array<string, string> $envFileValues
     */
    private function settings(string $configured = '', array $envFileValues = [], string $secret = ''): QueueSettings
    {
        return new QueueSettings(
            new ParameterBag(['queue_consumer' => $configured, 'queue_consume_secret' => $secret]),
            static fn (): array => $envFileValues
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->unsetRealEnv(QueueSettings::ENV_MODE);
        $this->unsetRealEnv(QueueSettings::ENV_BINARY);
    }

    public function testTheRealEnvironmentWinsOverDotEnvAndConfig(): void
    {
        $this->setRealEnv(QueueSettings::ENV_MODE, 'worker');

        $settings = $this->settings('binary', [QueueSettings::ENV_MODE => 'api']);

        $this->assertSame(QueueConsumerMode::Worker, $settings->mode());
        $this->assertSame('env', $settings->origin());
    }

    public function testDotEnvWinsOverConfig(): void
    {
        $settings = $this->settings('binary', [QueueSettings::ENV_MODE => 'api']);

        $this->assertSame(QueueConsumerMode::Api, $settings->mode());
        $this->assertSame('dotenv', $settings->origin());
    }

    public function testDotEnvPopulatesTheRealEnvironmentButStaysDotEnv(): void
    {
        // The loader copies private/.env into the process environment: the same value present in both is still the file's.
        $this->setRealEnv(QueueSettings::ENV_MODE, 'api');

        $settings = $this->settings('binary', [QueueSettings::ENV_MODE => 'api']);

        $this->assertSame(QueueConsumerMode::Api, $settings->mode());
        $this->assertSame('dotenv', $settings->origin());
    }

    public function testTheConfigurationIsUsedWhenTheEnvironmentSaysNothing(): void
    {
        $settings = $this->settings('binary');

        $this->assertSame(QueueConsumerMode::Binary, $settings->mode());
        $this->assertSame('config', $settings->origin());
    }

    public function testWithNothingSetUnderTheBinaryARequestConsumes(): void
    {
        // A supervised consumer is a PHP process kept alive per wiki: under the binary too, it is asked for, never the default.
        $this->setRealEnv(QueueSettings::ENV_BINARY, '1');

        $settings = $this->settings();

        $this->assertSame(QueueConsumerMode::Request, $settings->mode());
        $this->assertSame('default', $settings->origin());
        $this->assertTrue($settings->isServedByBinary());
    }

    public function testTheBinaryConsumesWhenAskedTo(): void
    {
        $this->setRealEnv(QueueSettings::ENV_BINARY, '1');

        $settings = $this->settings('', [QueueSettings::ENV_MODE => 'binary']);

        $this->assertSame(QueueConsumerMode::Binary, $settings->mode());
        $this->assertSame('dotenv', $settings->origin());
    }

    public function testWithNothingSetARequestConsumes(): void
    {
        $settings = $this->settings();

        $this->assertSame(QueueConsumerMode::Request, $settings->mode());
        $this->assertSame('default', $settings->origin());
        $this->assertFalse($settings->isServedByBinary());
    }

    public function testAnExplicitModeIsKeptUnderTheBinary(): void
    {
        $this->setRealEnv(QueueSettings::ENV_BINARY, '1');

        $settings = $this->settings('worker');

        $this->assertSame(QueueConsumerMode::Worker, $settings->mode());
        $this->assertSame('config', $settings->origin());
    }

    public function testAnUnknownValueFallsBackToTheDefaultAndSaysSo(): void
    {
        $this->setRealEnv(QueueSettings::ENV_MODE, 'cronjob');

        $settings = $this->settings();

        $this->assertSame(QueueConsumerMode::Request, $settings->mode());
        $this->assertTrue($settings->isUnknownValue());
        $this->assertSame('cronjob', $settings->rawValue());
    }

    public function testAKnownValueIsNotUnknown(): void
    {
        $settings = $this->settings('worker');

        $this->assertSame(QueueConsumerMode::Worker, $settings->mode());
        $this->assertFalse($settings->isUnknownValue());
        $this->assertSame('worker', $settings->rawValue());
    }

    /** `command` and `request-spawn` were modes of the development versions: a wiki that still says one gets the default, and the health page says where to correct it. */
    #[DataProvider('removedModes')]
    public function testARemovedModeFallsBackToTheDefault(string $removed): void
    {
        $settings = $this->settings($removed);

        $this->assertSame(QueueConsumerMode::Request, $settings->mode());
        $this->assertTrue($settings->isUnknownValue());
        $this->assertSame($removed, $settings->rawValue());
    }

    /** @return array<string, array{string}> */
    public static function removedModes(): array
    {
        return ['command' => ['command'], 'request-spawn' => ['request-spawn']];
    }

    public function testTheValueIsReadCaseInsensitively(): void
    {
        $this->assertSame(QueueConsumerMode::Api, $this->settings(' API ')->mode());
    }

    public function testTheConsumeSecretComesFromTheConfiguration(): void
    {
        $this->assertSame('s3cret', $this->settings('api', [], 's3cret')->consumeSecret());
        $this->assertSame('', $this->settings()->consumeSecret());
    }

    public function testOnlyThePassagesThatEndWithTheirTimeAreBudgeted(): void
    {
        $budgeted = array_filter(QueueConsumerMode::cases(), static fn (QueueConsumerMode $mode) => $mode->isBudgeted());

        $this->assertEqualsCanonicalizing(
            [QueueConsumerMode::Request, QueueConsumerMode::Api],
            array_values($budgeted)
        );
    }
}
