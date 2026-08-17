<?php

namespace YesWiki\Search\Entity;

/** One Content as the search index holds it (ticket 18 / ADR-0015). */
final class IndexedContent
{
    /**
     * @param string                $tag         the Content
     * @param string                $contentType '' for a page, else entry/user/file/form/comment/list
     * @param string                $formId      the entry's form, '' for anything else
     * @param string                $title       what the Content goes by in a result list
     * @param string                $pageReadAcl the row's *effective* read ACL, defaults already resolved
     * @param string                $owner       needed to evaluate a `%` (owner) read ACL in SQL
     * @param string                $updatedAt   'Y-m-d H:i:s'
     * @param array<string, string> $buckets     ACL expression => searchable text
     * @param list<string>          $keywords    the Content's keywords, for the `tags=` filter
     */
    public function __construct(
        public readonly string $tag,
        public readonly string $contentType,
        public readonly string $formId,
        public readonly string $title,
        public readonly string $pageReadAcl,
        public readonly string $owner,
        public readonly string $updatedAt,
        public readonly array $buckets,
        public readonly array $keywords = [],
    ) {
    }

    /** Whether this Content puts anything in the index at all. */
    public function isEmpty(): bool
    {
        if (trim($this->title) !== '') {
            return false;
        }
        foreach ($this->buckets as $text) {
            if (trim($text) !== '') {
                return false;
            }
        }

        return true;
    }
}
