<?php

namespace YesWiki\Content\Entity;

/** One thing in a list, in the shape a Presentation renders. */
final class Item
{
    /**
     * @param string       $id          what keys this item in a list -- a page tag, or a feed
     *                                  item's permalink. Never shown.
     * @param string       $title       the heading. Every Item has one; a Source with nothing
     *                                  better falls back to the id rather than rendering blank.
     * @param string|null  $subtitle    the line under it
     * @param string|null  $description prose, as HTML -- a feed's summary, a form's long text
     * @param string|null  $image       a URL, already resolved: a Presentation cannot know how
     *                                  to turn a filename into one, and the two Sources do it
     *                                  differently (an attachment path, or whatever the feed said)
     * @param string|null  $url         where clicking it goes
     * @param string|null  $date        ISO 8601, so a Presentation can both sort and format it
     * @param string|null  $badge       a short marker floated over the corner
     * @param list<string> $categories  tags, keywords, feed categories
     * @param string|null  $ctaUrl      where the item's button goes, when it has one. A
     *                                  Source resolves it: "let them edit this" is a URL
     *                                  only whatever supplied the item knows how to build
     * @param string|null  $ctaLabel    what that button says
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $subtitle = null,
        public readonly ?string $description = null,
        public readonly ?string $image = null,
        public readonly ?string $url = null,
        public readonly ?string $date = null,
        public readonly ?string $badge = null,
        public readonly array $categories = [],
        public readonly ?string $ctaUrl = null,
        public readonly ?string $ctaLabel = null,
    ) {
    }

    /**
     * What a Twig template sees.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'image' => $this->image,
            'url' => $this->url,
            'date' => $this->date,
            'badge' => $this->badge,
            'categories' => $this->categories,
            'ctaUrl' => $this->ctaUrl,
            'ctaLabel' => $this->ctaLabel,
        ];
    }
}
