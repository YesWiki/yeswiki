<?php

namespace YesWiki\Kernel\Service\Queue;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Kernel\Queue\RunsUnbudgeted;
use YesWiki\Kernel\Service\Journal;

/**
 * The bus the wiki dispatches on (messenger / scheduler): a message is always stored in the queue, never handled in the request that sent it.
 *
 * Unless the message must not run on a request's budget (RunsUnbudgeted) and no consumer would
 * take it soon: then it is still stored, and the request that sent it runs it once its response
 * has left (QueueRequestTrigger::pin()).
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

    /**
     * @param \Closure(): QueueRequestTrigger $requestTrigger this request's end, built only when a message that must not run on a request's budget is sent
     */
    public function __construct(
        private readonly QueueTransport $transport,
        QueueHandlerLocator $handlers,
        private readonly Journal $journal,
        #[AutowireServiceClosure(QueueRequestTrigger::class)]
        private readonly \Closure $requestTrigger,
    ) {
        $this->handling = new MessageBus([new HandleMessageMiddleware($handlers)]);
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);

        if ($envelope->last(ReceivedStamp::class) !== null) {
            return $this->handling->dispatch($envelope);
        }

        $runner = $message instanceof RunsUnbudgeted ? ($this->requestTrigger)() : null;
        if ($runner !== null && !$runner->runsItself()) {
            $runner = null;
        }
        if ($runner !== null) {
            $envelope = $envelope->with(new DelayStamp(RunsUnbudgeted::HELD_FOR_THE_SENDER_SECONDS * 1000));
        }

        try {
            $sent = $this->transport->send($envelope);
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

        $id = $sent->last(TransportMessageIdStamp::class)?->getId();
        if ($runner !== null && $id !== null) {
            $runner->pin((int)$id);
        }

        return $sent;
    }
}
