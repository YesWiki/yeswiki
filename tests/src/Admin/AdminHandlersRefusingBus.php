<?php

namespace YesWiki\Test\Admin;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/** A bus whose queue does not take the message: what it answers carries no TransportMessageIdStamp, as QueueBus's does when sending failed (it never breaks the page). */
final class AdminHandlersRefusingBus implements MessageBusInterface
{
    /** @var list<object> what it was asked to queue */
    public array $asked = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->asked[] = $message;

        return Envelope::wrap($message, $stamps);
    }
}
