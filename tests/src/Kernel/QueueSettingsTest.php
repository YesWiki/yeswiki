<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\Queue\QueueSettings;

/** Which consumption mode the wiki runs in: the `queue_consumer` parameter, the default when it says nothing. */
class QueueSettingsTest extends TestCase
{
    private function settings(string $configured = '', string $secret = ''): QueueSettings
    {
        return new QueueSettings(new ParameterBag(['queue_consumer' => $configured, 'queue_consume_secret' => $secret]));
    }

    /** The environment is resolved into the `queue_consumer` parameter before the setting is read: the setting reads that parameter, nothing else. */
    public function testTheModeIsTheParameterTheEnvironmentAlreadyResolved(): void
    {
        $this->assertSame(QueueConsumerMode::Api, $this->settings('api')->mode());
        $this->assertFalse($this->settings('api')->isUnknownValue());

        $settings = $this->settings('nope');
        $this->assertSame(QueueConsumerMode::Request, $settings->mode(), 'an unknown value gets the default');
        $this->assertTrue($settings->isUnknownValue());
        $this->assertSame('nope', $settings->rawValue());
    }

    public function testNoChoiceIsTheRequestModeAndIsNotUnknown(): void
    {
        foreach (['', '  '] as $blank) {
            $settings = $this->settings($blank);
            $this->assertSame(QueueConsumerMode::Request, $settings->mode());
            $this->assertFalse($settings->isUnknownValue());
            $this->assertSame('', $settings->rawValue());
        }
        $this->assertSame(QueueConsumerMode::Request, (new QueueSettings(new ParameterBag([])))->mode());
    }

    /** `binary`, `command` and `request-spawn` were modes of the development versions: a wiki that still says one gets the default, and the health page says so. */
    #[DataProvider('notModes')]
    public function testAValueThatIsNoModeFallsBackToTheDefaultAndSaysSo(string $written): void
    {
        $settings = $this->settings($written);

        $this->assertSame(QueueConsumerMode::Request, $settings->mode());
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
        $this->assertSame('s3cret', $this->settings('api', 's3cret')->consumeSecret());
        $this->assertSame('', $this->settings()->consumeSecret());
    }

    /** A secret that is a list in the configuration is no secret. */
    public function testASecretThatIsNotAScalarIsNone(): void
    {
        $settings = new QueueSettings(new ParameterBag(['queue_consume_secret' => ['x']]));

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
