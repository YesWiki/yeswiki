<?php

namespace YesWiki\Content\Entity;

use YesWiki\Kernel\Component\Setting;

/**
 * A **Source**: an action that supplies Items for a Presentation to render, rather than
 * rendering a list of its own.
 *
 * `{{entrylist}}` over a form, `{{syndication}}` over a feed, `{{listpages}}` over pages.
 * They have nothing in common except that each can answer "what is in this list?" -- so
 * that is the whole interface, and it is what lets `template="card"` mean one thing.
 *
 * A Source keeps its own tag and its own parameters. This is not a step towards one
 * `{{list source=…}}` action; ADR-0017 rejected that, and what is shared is only what the
 * list is rendered *through*.
 */
interface SuppliesItems
{
    /**
     * What this Source is called where a Presentation asks which one to use -- "the entries
     * of a form", "an RSS feed", "the pages of this wiki".
     */
    public static function sourceLabel(): string;

    /**
     * What a Presentation must be told to point at this Source: a form id, a feed url, a
     * tag filter. Offered alongside the presentation's own settings, shown only when this
     * Source is the one chosen.
     *
     * Declared by the Source, so **adding one is a change to that action and nothing else**
     * -- no presentation is edited, and none knows the list. That is the whole point of the
     * split, and the thing to check any future Source against.
     *
     * @return list<Setting>
     */
    public static function sourceSettings(): array;

    /**
     * The list, in render order.
     *
     * Called with the action's arguments already formatted, so an implementation reads
     * `$this->arguments` exactly as its `run()` does.
     *
     * @return list<Item>
     */
    public function items(): array;
}
