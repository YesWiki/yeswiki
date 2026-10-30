<?php

namespace YesWiki\Contact\Service;

use YesWiki\Files\Service\Storage;

/**
 * What the wiki remembers of each digest period, as the modification time of two files under `private/digests/`.
 *
 * `<period>.last` says when the period went out to everybody: written once a digest has been
 * sent, by whoever sent it (the queue's handler or the `contact:send-digest` command). A period
 * that failed has no new one, so it is not mistaken for one that was served.
 *
 * `<period>.tried` says when the queue last began to send it, successfully or not. The schedule
 * waits a floor from the later of the two: a digest that failed comes back one floor later
 * instead of at every tick, which would mail again those it had reached.
 */
class DigestMarkers
{
    public const DIRECTORY = 'private/digests';

    public function __construct(private readonly Storage $storage)
    {
    }

    /** When the period last went out; null if it never did, or if nobody wrote it down. */
    public function lastSentAt(string $period): ?\DateTimeImmutable
    {
        return $this->timeOf($this->file($period, 'last'));
    }

    /** When the queue last began to send the period; null if it never did. */
    public function lastTriedAt(string $period): ?\DateTimeImmutable
    {
        return $this->timeOf($this->file($period, 'tried'));
    }

    /**
     * Notes that the period went out now.
     *
     * @throws \YesWiki\Files\Exception\StorageException when the marker cannot be written
     */
    public function markSent(string $period): void
    {
        $this->storage->write($this->file($period, 'last'), (string)time());
    }

    /**
     * Notes that the queue is about to send the period.
     *
     * @throws \YesWiki\Files\Exception\StorageException when the marker cannot be written
     */
    public function markTried(string $period): void
    {
        $this->storage->write($this->file($period, 'tried'), (string)time());
    }

    private function timeOf(string $file): ?\DateTimeImmutable
    {
        if (!$this->storage->fileExists($file)) {
            return null;
        }

        return new \DateTimeImmutable('@' . $this->storage->lastModified($file));
    }

    private function file(string $period, string $kind): string
    {
        if (!in_array($period, MailSubscriptions::PERIODS, true)) {
            throw new \InvalidArgumentException("Unknown period '$period'");
        }

        return self::DIRECTORY . '/' . $period . '.' . $kind;
    }
}
