<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use YesWiki\Kernel\Service\Queue\QueueTransport;

/**
 * The transport as one pass's Worker sees it: it asks the pass whether to take another message before it claims one, and it never lets an unreadable row end the pass.
 *
 * Messenger's Worker would stop on the first MessageDecodingFailedException, and a blocking
 * consumer with it; the transport has already moved that row to `failed`, so the receiver has it
 * recorded and goes on to the next one.
 *
 * A message whose holder was declared dead while it ran is in `failed` by the time the Worker
 * acks it: the ack leaves it there, and the pass's observer says so.
 *
 * Built for a single pass and dropped with it.
 */
final class QueuePassReceiver implements ReceiverInterface
{
    /** Why the last get() came back empty: one of QueuePassReport's reasons. */
    private ?string $stopReason = null;

    private bool $taken = false;

    /**
     * @param \Closure(): ?string $guard    asked before each claim: a QueuePassReport reason to take nothing, or null to go on
     * @param QueuePassObserver   $observer told of each row the transport could not decode, and of each message that was no longer this pass's when it was done
     * @param int|null            $onlyId   that message and nothing else, once, whether or not its delay is over
     */
    public function __construct(
        private readonly QueueTransport $transport,
        private readonly \Closure $guard,
        private readonly QueuePassObserver $observer,
        private readonly ?int $onlyId = null,
    ) {
    }

    /** @return list<Envelope> */
    public function get(): iterable
    {
        while (true) {
            $this->stopReason = ($this->guard)();
            if ($this->stopReason !== null) {
                return [];
            }

            try {
                $envelopes = $this->onlyId === null ? [...$this->transport->get()] : $this->takeOnlyOne();
            } catch (UndecodableMessageException $undecodable) {
                $this->observer->undecodable($undecodable);
                continue;
            }

            if ($envelopes === []) {
                $this->stopReason = QueuePassReport::EMPTY;
            }

            return $envelopes;
        }
    }

    public function ack(Envelope $envelope): void
    {
        if (!$this->transport->release($envelope)) {
            $this->observer->declaredKilledBeforeItEnded($envelope);
        }
    }

    public function reject(Envelope $envelope): void
    {
        if (!$this->transport->release($envelope)) {
            $this->observer->declaredKilledBeforeItEnded($envelope);
        }
    }

    /** Why the pass took nothing more, when it did; null while it is still taking messages. */
    public function stopReason(): ?string
    {
        return $this->stopReason;
    }

    /** @return list<Envelope> */
    private function takeOnlyOne(): array
    {
        if ($this->taken) {
            return [];
        }
        $this->taken = true;
        $envelope = $this->transport->getById((int)$this->onlyId);

        return $envelope === null ? [] : [$envelope];
    }
}
