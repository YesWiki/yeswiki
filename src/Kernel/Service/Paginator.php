<?php

namespace YesWiki\Kernel\Service;

/**
 * Minimal pagination helper.
 *
 * Replaces `src/vendor/Pager` -- 1706 lines of vendored PEAR (PHP4-era, `&Pager::factory()`,
 * session support, form-POST paging, a dozen render modes) that existed for exactly one
 * caller using exactly two of its features: the current page's slice of items, and a strip
 * of page links. Wave-two ticket 02 rewrote it rather than taking a Composer dependency.
 *
 * Behaviour reproduced from PEAR's `Jumping` mode (the value of `BAZ_MODE_DIVISION`):
 * page numbers are shown in fixed blocks of `delta`, so pages 1..12 are listed together,
 * then 13..24, rather than sliding a window around the current page.
 *
 * Deliberately not reproduced: sessions, POST paging, `%d` filename placeholders, and the
 * `altNext`/`nextImg` distinction (PEAR emitted one as `alt`, the other as link text; with
 * both set to the same string, as the only caller does, they are indistinguishable).
 *
 * URL building is the caller's job on purpose -- see renderLinks(). Ticket 05 folds this
 * into the Content module.
 */
class Paginator
{
    /** @var list<mixed> */
    private array $items;
    private int $perPage;
    private int $delta;
    private int $totalPages;
    private int $currentPage;

    /**
     * @param array<mixed> $items      every item, unpaginated (re-indexed to a list)
     * @param int         $perPage     items per page (>= 1)
     * @param int         $currentPage 1-based; clamped into range
     * @param int         $delta       how many page numbers form one block
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
     * Render the `<li>` items for a `yw-pagination` list. Returns '' when there is
     * nothing to page through, matching PEAR's empty `links` for a single page.
     *
     * The caller supplies `$url` and `$extraVars` and therefore owns every question about
     * what a page link should point at. That is not indirection for its own sake: the one
     * caller strips `wiki` from the query string before passing it here, which looks like
     * it loses the page tag outside rewrite mode. That behaviour is preserved untouched
     * by ticket 02 -- a dead-code purge is the wrong place to change URL semantics -- but
     * it should not be baked into this class as though it were correct.
     *
     * @param string               $url       everything before the '?'
     * @param array<string, mixed> $extraVars query parameters to carry across pages
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
        $extraVars['pageID'] = $page; // PEAR's default urlVar; kept so existing links stay valid

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
