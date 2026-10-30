<?php

namespace YesWiki\Kernel\Service\Queue;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use YesWiki\Kernel\Service\Journal;

/**
 * The bus the wiki dispatches on (messenger / scheduler): a message is always stored in the queue, never handled in the request that sent it.
 *
 * That is the point of having it, and what separates it from Messenger's usual bus: with no
 * transport routing to configure and no "sync" transport to fall back on, a page that dispatches
 * cannot end up running the work, or failing with it. The one exception is a message the consumer
 * has just taken from the queue, which carries a ReceivedStamp: it goes through the handlers,
 * and that is the only way a handler ever runs.
 *
 * Dispatching never breaks the page. A queue that cannot be written to -- table not created yet
 * (a wiki not migrated, an upgrade or an installation under way), a database that is down -- is
 * journaled, and the envelope comes back without the stamp that says where it was stored.
 *
 * Except inside a transaction the caller opened on the queue's connection: there the failed send
 * has already doomed the caller's commit (DbService marks it rollback-only) and, on PostgreSQL,
 * aborted every statement after it. Swallowed, the failure would surface later as a refused
 * commit with nothing to say why; it is thrown where it happened.
 */
#[AsAlias(MessageBusInterface::class, public: true)]
class QueueBus implements MessageBusInterface
{
    private readonly MessageBus $handling;

    public function __construct(
        private readonly QueueTransport $transport,
        QueueHandlerLocator $handlers,
        private readonly Journal $journal,
    ) {
        $this->handling = new MessageBus([new HandleMessageMiddleware($handlers)]);
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);

        if ($envelope->last(ReceivedStamp::class) !== null) {
            return $this->handling->dispatch($envelope);
        }

        try {
            return $this->transport->send($envelope);
        } catch (\Throwable $failed) {
            if ($this->transport->inTransaction()) {
                throw $failed;
            }
            $this->journal->error('Could not queue ' . $envelope->getMessage()::class . ': ' . $failed->getMessage(), [
                'action' => 'queue.dispatch_failed',
                'target' => $envelope->getMessage()::class,
                'exception' => $failed,
            ]);

            return $envelope;
        }
    }
}
