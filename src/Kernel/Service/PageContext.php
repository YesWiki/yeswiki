<?php

namespace YesWiki\Kernel\Service;

/**
 * The page being served: its tag, loaded record, requested method and metadata
 * (historic Wiki::$tag/$page/$method/$metadatas, including element writes like
 * `$wiki->page['body'] = ...`). This service owns the state; the Runtime seeds
 * it at boot with what Init derived from the URL.
 */
class PageContext
{
    protected ?string $tag = null;

    /** @var array<mixed>|null */
    protected $page;

    protected string $method = '';

    /** @var array<mixed> */
    protected $metadata = [];

    public function getTag(): string
    {
        return (string)($this->tag ?? '');
    }

    public function setTag(?string $tag): void
    {
        $this->tag = $tag;
    }

    /**
     * The page record loaded for the current request, or null before one is set.
     *
     * Its `body` is a decoded body (see YesWiki\Content\Entity\PageBody), never the JSON
     * text held in the column: whoever sets the record decodes it first (ticket 09).
     *
     * @return array<mixed>|null
     */
    public function getPage(): ?array
    {
        return $this->page ?: null;
    }

    /** @param array<mixed>|null $page */
    public function setPage(?array $page): void
    {
        $this->page = $page;
    }

    /** Write one field of the current page record (historic `$wiki->page['x'] = ...`). */
    public function setPageField(string $key, mixed $value): void
    {
        $this->page[$key] = $value;
    }

    /**
     * Set the current page record and align the tag with it (historic Wiki::SetPage()).
     */
    public function assignPage(mixed $page): void
    {
        if (!empty($page)) {
            $this->page = $page;
            if (!empty($page['tag'])) {
                $this->tag = $page['tag'];
            }
        }
    }

    /** Save time of the current page record, '' when none is loaded (historic GetPageTime()). */
    public function getPageTime(): string
    {
        $page = $this->getPage();

        return empty($page['time']) ? '' : $page['time'];
    }

    /** The requested method exactly as given ('iframe', 'editiframe', ...). */
    public function getRawMethod(): string
    {
        return $this->method;
    }

    /**
     * The effective method (historic Wiki::GetMethod()): the iframe variants render
     * through their non-iframe counterparts.
     */
    public function getMethod(): string
    {
        if ($this->method == 'iframe') {
            return 'show';
        } elseif ($this->method == 'editiframe') {
            return 'edit';
        }

        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /** @return array<mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @param array<mixed> $metadata */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }
}
