<?php

namespace YesWiki\Search\Message;

use YesWiki\Kernel\Queue\Deduplicated;

/**
 * "Bring this Content's search index rows up to date": what a save, a rename, a form change or a migration sends, one per Content.
 *
 * It carries the tag only: the handler reads the Content as it is when it runs, so saving a
 * Content twice before then still costs one reindex, and the second save is absorbed into the
 * first while it waits (Deduplicated).
 */
final class ReindexContent implements Deduplicated
{
    public const UNIQUE_KEY_PREFIX = 'search:reindex:';

    public function __construct(public readonly string $tag)
    {
    }

    public function deduplicationKey(): string
    {
        return self::UNIQUE_KEY_PREFIX . $this->tag;
    }
}
