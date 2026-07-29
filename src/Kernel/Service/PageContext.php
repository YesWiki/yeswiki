<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Wiki;

/**
 * The page being served by the current request: its tag, the record loaded for it, the
 * handler method being applied, and the page metadata.
 *
 * Ticket 08 transition note: the state itself still lives on Wiki's public properties
 * ($tag, $page, $method, $metadatas) because legacy code reads and writes them directly
 * (including element writes like `$wiki->page['body'] = ...`). This service is the API
 * every new consumer must use; when the last direct property access is gone, the state
 * moves here and the Wiki dependency disappears.
 */
class PageContext
{
    protected Wiki $wiki;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
    }

    public function getTag(): string
    {
        return (string)($this->wiki->tag ?? '');
    }

    public function setTag(?string $tag): void
    {
        $this->wiki->tag = $tag;
    }

    /**
     * The page record loaded for the current request, or null before one is set.
     *
     * @return array<mixed>|null
     */
    public function getPage(): ?array
    {
        return $this->wiki->page ?: null;
    }

    /** @param array<mixed>|null $page */
    public function setPage(?array $page): void
    {
        $this->wiki->page = $page;
    }

    /** Write one field of the current page record (historic `$wiki->page['x'] = ...`). */
    public function setPageField(string $key, mixed $value): void
    {
        $this->wiki->page[$key] = $value;
    }

    /**
     * Set the current page record and align the tag with it (historic Wiki::SetPage()).
     */
    public function assignPage(mixed $page): void
    {
        if (!empty($page)) {
            $this->wiki->page = $page;
            if (!empty($page['tag'])) {
                $this->wiki->tag = $page['tag'];
            }
        }
    }

    /** Save time of the current page record, '' when none is loaded (historic GetPageTime()). */
    public function getPageTime(): string
    {
        $page = $this->getPage();

        return empty($page['time']) ? '' : $page['time'];
    }

    /** The raw handler method of the request ('show', 'edit', 'iframe', ...). */
    public function getRawMethod(): string
    {
        return (string)($this->wiki->method ?? '');
    }

    /**
     * The handler method with iframe variants mapped to what they display
     * (historic Wiki::GetMethod()).
     */
    public function getMethod(): string
    {
        $method = $this->getRawMethod();
        if ($method === 'iframe') {
            return 'show';
        }
        if ($method === 'editiframe') {
            return 'edit';
        }

        return $method;
    }

    public function setMethod(string $method): void
    {
        $this->wiki->method = $method;
    }

    /** @return array<mixed> */
    public function getMetadata(): array
    {
        return is_array($this->wiki->metadatas) ? $this->wiki->metadatas : [];
    }

    /** @param array<mixed> $metadata */
    public function setMetadata(array $metadata): void
    {
        $this->wiki->metadatas = $metadata;
    }
}
