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
 * The bus the wiki dispatches on: a message is always stored in the queue, never handled in the request that sent it;
 * only a message the consumer took (ReceivedStamp) reaches the handlers. Dispatching never breaks the page.
 */
#[AsAlias(MessageBusInterface::class, public: true)]
class QueueBus implements MessageBusInterface
{
    private readonly MessageBus $handling;

    /**
     * @param \Closure(): QueueRequestTrigger $requestTrigger
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
