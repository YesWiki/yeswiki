<?php

namespace YesWiki\Kernel\Service\Queue;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Queue\QueueConsumerMode;

/**
 * Which consumption mode the wiki runs in: the `queue_consumer` parameter, which the environment (the real one, then
 * private/.env) has already overridden when it says so; the default is `request`.
 */
class QueueSettings
{
    public const ENV_MODE = 'YESWIKI_QUEUE_CONSUMER';

    public function __construct(private readonly ParameterBagInterface $params)
    {
    }

    /** The mode the wiki consumes in; an unknown value does not stop the wiki, it gets the default (see isUnknownValue()). */
    public function mode(): QueueConsumerMode
    {
        return QueueConsumerMode::tryFrom(strtolower($this->rawValue())) ?? QueueConsumerMode::Request;
    }

    /** What the admin wrote, trimmed and as written; '' when nobody chose. */
    public function rawValue(): string
    {
        return trim($this->configured('queue_consumer'));
    }

    /** Whether something was written that is not a mode. */
    public function isUnknownValue(): bool
    {
        $value = $this->rawValue();

        return $value !== '' && QueueConsumerMode::tryFrom(strtolower($value)) === null;
    }

    /** The shared secret of the `api` mode; '' while none is set. */
    public function consumeSecret(): string
    {
        return $this->configured('queue_consume_secret');
    }

    private function configured(string $name): string
    {
        $value = $this->params->has($name) ? $this->params->get($name) : '';

        return is_scalar($value) ? (string)$value : '';
    }
}
