<?php

namespace YesWiki\Search\Message;

use YesWiki\Kernel\Queue\Deduplicated;

/**
 * "Bring this Content's search index rows up to date": what a save, a rename, a form change or a migration sends, one per Content.
 * It carries the tag only, so a second save while it waits is absorbed into the first.
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
