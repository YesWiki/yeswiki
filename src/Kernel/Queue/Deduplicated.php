<?php

namespace YesWiki\Kernel\Queue;

/**
 * Implemented by a message whose work only needs doing once more, however many times it was asked for: while one with the same key waits, the transport absorbs the next (QueueTransport::send()).
 *
 * For idempotent work: reindexing a Content, the next step of a fetch. Once the waiting message
 * has been taken, the key is free again, so whatever arrived during its run gets a run of its own.
 * A scheduled task's run is deduplicated the same way, on its task id, without saying so.
 */
interface Deduplicated
{
    /** `<module>:<what>`: what makes two such messages the same work. */
    public function deduplicationKey(): string;
}
