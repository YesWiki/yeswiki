<?php

namespace YesWiki\Test\Kernel;

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

        $settings = $this->settings('command', [QueueSettings::ENV_MODE => 'api']);

        $this->assertSame(QueueConsumerMode::Worker, $settings->mode());
        $this->assertSame('env', $settings->origin());
    }

    public function testDotEnvWinsOverConfig(): void
    {
        $settings = $this->settings('command', [QueueSettings::ENV_MODE => 'api']);

        $this->assertSame(QueueConsumerMode::Api, $settings->mode());
        $this->assertSame('dotenv', $settings->origin());
    }

    public function testDotEnvPopulatesTheRealEnvironmentButStaysDotEnv(): void
    {
        // The loader copies private/.env into the process environment: the same value present in both is still the file's.
        $this->setRealEnv(QueueSettings::ENV_MODE, 'api');

        $settings = $this->settings('command', [QueueSettings::ENV_MODE => 'api']);

        $this->assertSame(QueueConsumerMode::Api, $settings->mode());
        $this->assertSame('dotenv', $settings->origin());
    }

    public function testTheConfigurationIsUsedWhenTheEnvironmentSaysNothing(): void
    {
        $settings = $this->settings('command');

        $this->assertSame(QueueConsumerMode::Command, $settings->mode());
        $this->assertSame('config', $settings->origin());
    }

    public function testWithNothingSetTheBinaryConsumes(): void
    {
        $this->setRealEnv(QueueSettings::ENV_BINARY, '1');

        $settings = $this->settings();

        $this->assertSame(QueueConsumerMode::Binary, $settings->mode());
        $this->assertSame('default', $settings->origin());
        $this->assertTrue($settings->isServedByBinary());
    }

    public function testWithNothingSetARequestConsumes(): void
    {
        $settings = $this->settings();

        $this->assertSame(QueueConsumerMode::Request, $settings->mode());
        $this->assertSame('default', $settings->origin());
        $this->assertFalse($settings->isServedByBinary());
    }

    public function testAnExplicitModeBeatsTheBinaryDefault(): void
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
        $settings = $this->settings('request-spawn');

        $this->assertSame(QueueConsumerMode::RequestSpawn, $settings->mode());
        $this->assertFalse($settings->isUnknownValue());
        $this->assertSame('request-spawn', $settings->rawValue());
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
            [QueueConsumerMode::Request, QueueConsumerMode::RequestSpawn, QueueConsumerMode::Command, QueueConsumerMode::Api],
            array_values($budgeted)
        );
    }
}
