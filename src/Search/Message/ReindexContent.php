<?php

namespace YesWiki\Search\Message;

use YesWiki\Kernel\Queue\UniqueStamp;

/**
 * "Bring this Content's search index rows up to date": what a save, a rename, a form change or a migration sends, one per Content.
 *
 * It carries the tag only: the handler reads the Content as it is when it runs, so saving a
 * Content twice before then still costs one reindex, and the message is sent under uniqueStamp()
 * so that the second save is absorbed into the first while it waits.
 */
final class ReindexContent
{
    public const UNIQUE_KEY_PREFIX = 'search:reindex:';

    public function __construct(public readonly string $tag)
    {
    }

    public function uniqueStamp(): UniqueStamp
    {
        return new UniqueStamp(self::UNIQUE_KEY_PREFIX . $this->tag);
    }
}
