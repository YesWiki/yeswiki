<?php

namespace YesWiki\Kernel\Service;

/** Minimal pagination helper. */
class Paginator
{
    /**
     * @var list<mixed>
     */
    private array $items;
    private int $perPage;
    private int $delta;
    private int $totalPages;
    private int $currentPage;

    /**
     * @param array<mixed> $items       every item, unpaginated (re-indexed to a list)
     * @param int          $perPage     items per page (>= 1)
     * @param int          $currentPage 1-based; clamped into range
     * @param int          $delta       how many page numbers form one block
     */
    public function __construct(array $items, int $perPage, int $currentPage = 1, int $delta = 12)
    {
        $this->items = array_values($items);
        $this->perPage = max(1, $perPage);
        $this->delta = max(1, $delta);
        $this->totalPages = max(1, (int)ceil(count($this->items) / $this->perPage));
        $this->currentPage = min(max(1, $currentPage), $this->totalPages);
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * The current page's slice.
     *
     * @return list<mixed>
     */
    public function getPageData(): array
    {
        return array_slice($this->items, ($this->currentPage - 1) * $this->perPage, $this->perPage);
    }

    /**
     * The page numbers to show, as one `delta`-sized block containing the current page.
     *
     * @return list<int>
     */
    public function getPageRange(): array
    {
        $block = intdiv($this->currentPage - 1, $this->delta);
        $first = $block * $this->delta + 1;
        $last = min($first + $this->delta - 1, $this->totalPages);

        return range($first, $last);
    }

    /**
     * Render the `<li>` items for a `yw-pagination` list.
     *
     * @param string                            $url       everything before the '?'
     * @param array<string, mixed>              $extraVars query parameters to carry across pages
     * @param array{prev: string, next: string} $labels
     */
    public function renderLinks(string $url, array $extraVars = [], array $labels = ['prev' => '&laquo;', 'next' => '&raquo;']): string
    {
        if ($this->totalPages < 2) {
            return '';
        }

        $link = function (int $page, string $text, string $modifier = '') use ($url, $extraVars): string {
            $href = $this->hrefFor($url, $extraVars, $page);

            return '<li class="yw-pagination__item' . $modifier . '">'
                . '<a class="yw-pagination__link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
                . $text
                . '</a></li>';
        };

        $out = '';
        if ($this->currentPage > 1) {
            $out .= $link($this->currentPage - 1, $labels['prev']);
        }
        foreach ($this->getPageRange() as $page) {
            $out .= $link(
                $page,
                (string)$page,
                $page === $this->currentPage ? ' yw-pagination__item--active' : ''
            );
        }
        if ($this->currentPage < $this->totalPages) {
            $out .= $link($this->currentPage + 1, $labels['next']);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $extraVars
     */
    private function hrefFor(string $url, array $extraVars, int $page): string
    {
        $extraVars['pageID'] = $page;

        return $url . '?' . http_build_query($extraVars);
    }

    /**
     * Read the requested page from a query bag, tolerating absent/garbage values.
     *
     * @param array<string, mixed> $query
     */
    public static function pageFromQuery(array $query, string $key = 'pageID'): int
    {
        $raw = $query[$key] ?? 1;

        return is_numeric($raw) ? max(1, (int)$raw) : 1;
    }
}
