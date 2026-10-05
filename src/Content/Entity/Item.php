<?php

namespace YesWiki\Content\Entity;

/** One thing in a list, in the shape a Presentation renders. */
final class Item
{
    /**
     * @param list<string>                                                                       $categories
     * @param array{filename: string, width: int, height: int, mode: string, token: string}|null $imageResize
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
        public readonly ?string $footer = null,
        public readonly ?string $badgeDate = null,
        public readonly ?array $imageResize = null,
    ) {
    }

    /** @return array<string, mixed> what a Twig template sees */
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
            'footer' => $this->footer,
            'badgeDate' => $this->badgeDate,
            'imageResize' => $this->imageResize,
        ];
    }
}
