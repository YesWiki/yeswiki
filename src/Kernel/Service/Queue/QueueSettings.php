<?php

namespace YesWiki\Kernel\Service\Queue;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Kernel\Queue\QueueConsumerMode;

/**
 * Which consumption mode the wiki runs in, and where that choice comes from.
 *
 * Precedence: the real environment, then private/.env, then `queue_consumer` in the configuration, then the default (`binary` under the Go binary, `request` otherwise). The binary does not set YESWIKI_QUEUE_CONSUMER, it sets a marker of its own, so an admin can still pick another mode under it and `binary` without the marker is detectable.
 */
class QueueSettings
{
    public const ENV_MODE = 'YESWIKI_QUEUE_CONSUMER';
    public const ENV_BINARY = 'YESWIKI_SERVED_BY_BINARY';

    public const ORIGIN_ENV = 'env';
    public const ORIGIN_DOTENV = 'dotenv';
    public const ORIGIN_CONFIG = 'config';
    public const ORIGIN_DEFAULT = 'default';

    /** @var callable(): array<string, string> */
    private $envFileValues;

    /**
     * @param (callable(): array<string, string>)|null $envFileValues private/.env content, injectable for tests (default: YesWikiLoader::envFileValues())
     */
    public function __construct(
        private readonly ParameterBagInterface $params,
        ?callable $envFileValues = null
    ) {
        $this->envFileValues = $envFileValues ?? static fn (): array => YesWikiLoader::envFileValues();
    }

    /** The mode the wiki consumes in; an unknown value does not stop the wiki, it gets the default (see isUnknownValue()). */
    public function mode(): QueueConsumerMode
    {
        return QueueConsumerMode::tryFrom(strtolower($this->resolve()['value']))
            ?? ($this->isServedByBinary() ? QueueConsumerMode::Binary : QueueConsumerMode::Request);
    }

    /**
     * Where the setting was found: 'env', 'dotenv', 'config', or 'default' when nobody chose.
     *
     * An unknown value keeps the origin it was found in, so the page can say where to correct it.
     */
    public function origin(): string
    {
        return $this->resolve()['origin'];
    }

    /** What the admin wrote, trimmed and as written; '' when nobody chose. */
    public function rawValue(): string
    {
        return $this->resolve()['value'];
    }

    /** Whether something was written that is not a mode. */
    public function isUnknownValue(): bool
    {
        $value = $this->resolve()['value'];

        return $value !== '' && QueueConsumerMode::tryFrom(strtolower($value)) === null;
    }

    public function isServedByBinary(): bool
    {
        $marker = getenv(self::ENV_BINARY);

        return $marker !== false && $marker !== '';
    }

    /** The shared secret of the `api` mode; '' while none is set. */
    public function consumeSecret(): string
    {
        return $this->configured('queue_consume_secret');
    }

    /**
     * @return array{value: string, origin: string}
     */
    private function resolve(): array
    {
        $real = getenv(self::ENV_MODE);
        $real = $real === false ? '' : trim($real);
        $file = trim(($this->envFileValues)()[self::ENV_MODE] ?? '');

        // The loader copies private/.env into the process environment without overriding what is already there: the real environment holds the file's value, or a different one that came first and wins. Equal values cannot be told apart, and the file is the likelier source.
        if ($real !== '' && $real !== $file) {
            return ['value' => $real, 'origin' => self::ORIGIN_ENV];
        }
        if ($file !== '') {
            return ['value' => $file, 'origin' => self::ORIGIN_DOTENV];
        }
        $configured = trim($this->configured('queue_consumer'));
        if ($configured !== '') {
            return ['value' => $configured, 'origin' => self::ORIGIN_CONFIG];
        }

        return ['value' => '', 'origin' => self::ORIGIN_DEFAULT];
    }

    private function configured(string $name): string
    {
        $value = $this->params->has($name) ? $this->params->get($name) : '';

        return is_scalar($value) ? (string)$value : '';
    }
}
