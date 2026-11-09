<?php

namespace YesWiki\Kernel\Queue;

/** Implemented by a message whose work only needs doing once more: while one with the same key waits, the transport absorbs the next. */
interface Deduplicated
{
    /** `<module>:<what>`: what makes two such messages the same work. */
    public function deduplicationKey(): string;
}
