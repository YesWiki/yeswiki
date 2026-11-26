<?php

namespace YesWiki\Kernel\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueTransport;

/**
 * Listens to one pass's Worker and writes down how each message ended, whatever the mode: the history row, and the
 * Journal entry when the failure is for good. Built for a single pass and dropped with it.
 */
final class QueuePassObserver implements EventSubscriberInterface
{
    private int $handled = 0;

    private float $startedAt = 0.0;

    public function __construct(
        private readonly QueueRecorder $recorder,
        private readonly Journal $journal,
        private readonly QueueConsumerMode $mode,
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onReceived',
            WorkerMessageHandledEvent::class => 'onHandled',
            WorkerMessageFailedEvent::class => ['onFailed', -200],
        ];
    }

    public function handled(): int
    {
        return $this->handled;
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->startedAt = $this->now();
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->handled++;
        $this->finished($event->getEnvelope(), QueueRecorder::HANDLED, null);
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $this->handled++;
        $cause = self::causeOf($event->getThrowable());
        $error = QueueFailureCause::ofThrowable($cause);
        $envelope = $event->getEnvelope();

        if ($event->willRetry()) {
            $this->finished($envelope, QueueRecorder::RETRIED, $error);

            return;
        }

        $this->finished($envelope, QueueRecorder::FAILED, $error);
        $type = $envelope->getMessage()::class;
        $this->journal->error("{$type} failed for good: {$cause->getMessage()}", [
            'action' => 'queue.message_failed',
            'target' => $type,
            'exception' => $cause,
        ]);
    }

    /** What the handler threw, rather than the HandlerFailedException the bus wrapped it in: the one ErrorDetailsStamp describes too. */
    private static function causeOf(\Throwable $failure): \Throwable
    {
        if ($failure instanceof HandlerFailedException) {
            return $failure->getWrappedExceptions()[array_key_first($failure->getWrappedExceptions())] ?? $failure;
        }

        return $failure;
    }

    /** The transport took a message it could not decode, and has put it in `failed`. */
    public function undecodable(UndecodableMessageException $undecodable): void
    {
        $error = 'undecodable: ' . $undecodable->getMessage();
        $this->recorder->messageFinished($undecodable->messageId, $undecodable->messageType, null, QueueRecorder::FAILED, $this->mode, $this->now(), $error);
        $this->journal->error("{$undecodable->messageType} cannot be read back from the queue: {$undecodable->getMessage()}", [
            'action' => 'queue.message_failed',
            'target' => $undecodable->messageType,
        ]);
    }

    /** A message that ended after its holder had been declared dead (QueueTransport::release() found it in `failed`). */
    public function declaredKilledBeforeItEnded(Envelope $envelope): void
    {
        $type = $envelope->getMessage()::class;
        $this->journal->info("{$type} ended after its consumer had been declared dead; it stays in the failed messages", [
            'action' => 'queue.message_ended_late',
            'target' => $type,
            'messageId' => (int)$envelope->last(TransportMessageIdStamp::class)?->getId(),
        ]);
    }

    /**
     * A message whose consumer died while handling it, recorded under the mode it was killed in (QueueTransport::reapKilled()).
     *
     * @param array{id: int, type: string, mode: string, deliveredAt: string} $killed
     */
    public function killed(array $killed, ?string $taskId): void
    {
        $mode = QueueConsumerMode::tryFrom($killed['mode']) ?? $this->mode;
        $deliveredAt = (float)(new \DateTimeImmutable($killed['deliveredAt']))->format('U');

        $this->recorder->messageFinished($killed['id'], $killed['type'], $taskId, QueueRecorder::KILLED, $mode, $deliveredAt, QueueTransport::killedCause($killed['mode']));
        $this->journal->error("{$killed['type']} was still running when its {$killed['mode']} consumer was stopped: too long for that mode", [
            'action' => 'queue.message_killed',
            'target' => $killed['type'],
            'mode' => $killed['mode'],
        ]);
    }

    /** Writes how a message ended; the Worker acknowledges it only after this returns, so a history that cannot be written must not throw. */
    private function finished(Envelope $envelope, string $status, ?string $error): void
    {
        $message = $envelope->getMessage();
        $type = $message::class;

        try {
            $this->recorder->messageFinished(
                (int)$envelope->last(TransportMessageIdStamp::class)?->getId(),
                $type,
                $message instanceof ScheduledTask ? $message->taskId() : null,
                $status,
                $this->mode,
                $this->startedAt,
                $error
            );
        } catch (\Throwable $unwritten) {
            $this->journal->error("{$type} ended ({$status}) but its history row could not be written: {$unwritten->getMessage()}", [
                'action' => 'queue.history_unwritten',
                'target' => $type,
                'exception' => $unwritten,
            ]);
        }
    }

    private function now(): float
    {
        return (float)$this->clock->now()->format('U.u');
    }
}
