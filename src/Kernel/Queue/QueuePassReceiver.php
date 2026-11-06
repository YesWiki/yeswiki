<?php

namespace YesWiki\Kernel\Queue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use YesWiki\Kernel\Service\Queue\QueueTransport;

/**
 * The transport as one pass's Worker sees it: it asks the pass whether to take another message before it claims one,
 * and it never lets an unreadable row end the pass. Built for a single pass and dropped with it. Given an upper id, it claims only the messages up to that one.
 */
final class QueuePassReceiver implements ReceiverInterface
{
    private ?string $stopReason = null;

    private bool $taken = false;

    /**
     * @param \Closure(): ?string $guard
     */
    public function __construct(
        private readonly QueueTransport $transport,
        private readonly \Closure $guard,
        private readonly QueuePassObserver $observer,
        private readonly ?int $onlyId = null,
        private readonly ?int $upToId = null,
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
                $envelopes = $this->onlyId !== null
                    ? $this->takeOnlyOne()
                    : [...($this->upToId === null ? $this->transport->get() : $this->transport->getUpTo($this->upToId))];
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
