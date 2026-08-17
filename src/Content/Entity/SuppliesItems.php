<?php

namespace YesWiki\Content\Entity;

use YesWiki\Kernel\Component\Setting;

/**
 * A **Source**: an action that supplies Items for a Presentation to render, rather than rendering a list of its own.
 */
interface SuppliesItems
{
    /**
     * What this Source is called where a Presentation asks which one to use -- "the entries of a form", "an RSS feed", "the pages of this wiki".
     */
    public static function sourceLabel(): string;

    /**
     * What a Presentation must be told to point at this Source: a form id, a feed url, a tag filter.
     *
     * @return list<Setting>
     */
    public static function sourceSettings(): array;

    /**
     * ...and which of them, how many, in what order.
     *
     * @return list<Setting>
     */
    public static function sourceSelectionSettings(): array;

    /**
     * The list, in render order.
     *
     * @return list<Item>
     */
    public function items(): array;
}
