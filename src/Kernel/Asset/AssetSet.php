<?php

namespace YesWiki\Kernel\Asset;

/**
 * An ordered, de-duplicated collection of declared assets -- what a capture scope returns
 * and what an emission point renders.
 *
 * Deduplication is identity on AssetEntry::$key (a resolved URL, or a content hash for an
 * inline block), which is the same rule the browser-side registry applies to what a fragment
 * carries. The old registry compared generated HTML with `!strpos(...)`, which is falsy at
 * offset 0 -- so the first stylesheet registered always failed its own duplicate check.
 *
 * @see docs/adr/0014-assets-are-declared-by-a-render-not-accumulated-by-a-request.md
 */
final class AssetSet
{
    /** @var array<string, AssetEntry> keyed by AssetEntry::$key, insertion-ordered */
    private array $entries = [];

    /** @param iterable<AssetEntry> $entries */
    public function __construct(iterable $entries = [])
    {
        foreach ($entries as $entry) {
            $this->add($entry);
        }
    }

    /** First registration of a key wins: it is the one whose position callers reasoned about. */
    public function add(AssetEntry $entry): void
    {
        if (!isset($this->entries[$entry->key])) {
            $this->entries[$entry->key] = $entry;
        }
    }

    public function merge(self $other): void
    {
        foreach ($other->entries as $entry) {
            $this->add($entry);
        }
    }

    public function has(string $key): bool
    {
        return isset($this->entries[$key]);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Registration order, except that `first` entries lead -- they are the ones inline page
     * markup calls into at parse time, so they cannot sit in the deferred queue.
     *
     * @return list<AssetEntry>
     */
    public function entries(): array
    {
        $first = [];
        $rest = [];
        foreach ($this->entries as $entry) {
            if ($entry->first) {
                $first[] = $entry;
            } else {
                $rest[] = $entry;
            }
        }

        return array_merge($first, $rest);
    }

    /** @return list<string> the URLs this set loads, ignoring inline blocks */
    public function urls(): array
    {
        $urls = [];
        foreach ($this->entries as $entry) {
            $url = $entry->url();
            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    public function toHtml(): string
    {
        $html = '';
        foreach ($this->entries() as $entry) {
            $html .= $entry->toHtml();
        }

        return $html;
    }

    /**
     * The same assets, as an htmx out-of-band swap appending them to <head>.
     *
     * A fragment's assets must not share the fragment's lifetime: rendered inline, deleting
     * a map field's preview card in the form designer would take `leaflet.css` out of the
     * document with it and unstyle every other map preview still on screen -- while the
     * browser-side registry still believed it was loaded. Swapped into <head>, they outlive
     * whatever asked for them, which is what "assets are never unloaded" actually requires.
     *
     * htmx applies a non-outerHTML out-of-band swap using the *children* of the marked
     * element, hence the wrapper.
     */
    public function toOutOfBandHtml(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        return '<div hx-swap-oob="beforeend:head">' . "\n" . $this->toHtml() . '</div>' . "\n";
    }
}
