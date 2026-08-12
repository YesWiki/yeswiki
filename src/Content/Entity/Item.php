<?php

namespace YesWiki\Content\Entity;

/**
 * One thing in a list, in the shape a Presentation renders.
 *
 * Derived at render time and **never stored**: an Item is a view of Content -- or of a feed
 * entry, or of a page -- not a row. Nothing reads one back, nothing indexes one, and no
 * migration will ever be needed for one.
 *
 * It exists so that `template="card"` means the same thing whatever supplied the list. A
 * card asks for a picture, a heading, a line under it, a badge and some prose; a bazar entry
 * answers with fields a webmaster named, and an RSS item answers with the fields RSS has.
 * Neither of those shapes is renderable on its own, and the presentations used to be written
 * against one of them each -- which is why there were two unrelated template families
 * sharing a parameter name (ticket 37).
 *
 * The slots are the ones the presentations already draw. `badge` is `displayfields`'
 * `floating`, renamed: a card floats a short marker over its corner, and "floating" names
 * where it is drawn rather than what it is for.
 */
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
    ) {
    }

    /**
     * What a Twig template sees.
     *
     * An array rather than the object: the presentations are Twig, `item.image` reads the
     * same either way, and a template that asked for a slot this did not have would fail
     * loudly on an object and quietly on an array. Quietly is right here -- a Source that
     * has no date is not an error, it is a Source without dates.
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
        ];
    }
}
