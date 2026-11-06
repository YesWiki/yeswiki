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

    /**
     * @param string|null $env    what the real environment says; null when it says nothing
     * @param string|null $dotenv what private/.env says; null when it says nothing
     */
    #[DataProvider('precedence')]
    public function testTheMostSpecificSourceWins(?string $env, ?string $dotenv, string $config, QueueConsumerMode $mode, string $origin): void
    {
        if ($env !== null) {
            $this->setRealEnv(QueueSettings::ENV_MODE, $env);
        }

        $settings = $this->settings($config, $dotenv === null ? [] : [QueueSettings::ENV_MODE => $dotenv]);

        $this->assertSame($mode, $settings->mode());
        $this->assertSame($origin, $settings->origin());
        $this->assertFalse($settings->isUnknownValue());
        $this->assertSame($origin === 'default' ? '' : $mode->value, $settings->rawValue());
    }

    /** @return array<string, array{?string, ?string, string, QueueConsumerMode, string}> */
    public static function precedence(): array
    {
        return [
            'the real environment wins over private/.env and the configuration' => ['worker', 'api', 'request', QueueConsumerMode::Worker, 'env'],
            'private/.env wins over the configuration' => [null, 'api', 'worker', QueueConsumerMode::Api, 'dotenv'],
            // The loader copies private/.env into the process environment: the same value present in both is still the file's.
            'the same value in both is the file\'s' => ['api', 'api', 'worker', QueueConsumerMode::Api, 'dotenv'],
            'the configuration alone' => [null, null, 'worker', QueueConsumerMode::Worker, 'config'],
            'a blank environment falls through to the configuration' => [' ', null, 'worker', QueueConsumerMode::Worker, 'config'],
            'a blank private/.env falls through to the configuration' => [null, '  ', 'worker', QueueConsumerMode::Worker, 'config'],
            'a blank configuration is no choice' => [null, null, '  ', QueueConsumerMode::Request, 'default'],
            'nothing set: a request consumes' => [null, null, '', QueueConsumerMode::Request, 'default'],
        ];
    }

    /** A supervised consumer is a PHP process kept alive per wiki: the binary marker says so, and does not choose a mode. */
    public function testTheBinaryMarkerIsSeenOnlyWhenSet(): void
    {
        $this->assertFalse($this->settings()->isServedByBinary());

        $this->setRealEnv(QueueSettings::ENV_BINARY, '1');
        $this->assertTrue($this->settings()->isServedByBinary());
        $this->assertSame(QueueConsumerMode::Request, $this->settings()->mode(), 'under the binary too, a worker is asked for, never the default');

        $this->setRealEnv(QueueSettings::ENV_BINARY, '');
        $this->assertFalse($this->settings()->isServedByBinary());
    }

    /** `binary`, `command` and `request-spawn` were modes of the development versions: a wiki that still says one gets the default, and the health page says where to correct it. */
    #[DataProvider('notModes')]
    public function testAValueThatIsNoModeFallsBackToTheDefaultAndSaysSo(string $written): void
    {
        $this->setRealEnv(QueueSettings::ENV_MODE, $written);

        $settings = $this->settings('worker');

        $this->assertSame(QueueConsumerMode::Request, $settings->mode());
        $this->assertSame('env', $settings->origin(), 'an unknown value keeps its origin');
        $this->assertTrue($settings->isUnknownValue());
        $this->assertSame($written, $settings->rawValue());
    }

    /** @return array<string, array{string}> */
    public static function notModes(): array
    {
        return ['cronjob' => ['cronjob'], 'binary' => ['binary'], 'command' => ['command'], 'request-spawn' => ['request-spawn']];
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

    /** A secret that is a list in the configuration is no secret. */
    public function testASecretThatIsNotAScalarIsNone(): void
    {
        $settings = new QueueSettings(new ParameterBag(['queue_consume_secret' => ['x']]), static fn (): array => []);

        $this->assertSame('', $settings->consumeSecret());
    }

    public function testOnlyThePassagesThatStopByThemselvesEndByThemselves(): void
    {
        $endingByThemselves = array_filter(QueueConsumerMode::cases(), static fn (QueueConsumerMode $mode) => $mode->endsByItself());

        $this->assertEqualsCanonicalizing(
            [QueueConsumerMode::Request, QueueConsumerMode::Api],
            array_values($endingByThemselves)
        );
    }
}
