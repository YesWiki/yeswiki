<?php

namespace YesWiki\Identity\Entity;

/**
 * How an account shows its face: a picture, or a coloured disc with its initials on it.
 *
 * A value object rather than markup, because the two cases are one thing to a caller --
 * "draw this account" -- and because the colours are arithmetic worth testing without a
 * template in the way. `@core/_avatar.twig` is the one place that turns this into HTML.
 */
class Avatar
{
    /** The account this is the face of -- its name, which is its tag. */
    public readonly string $name;

    /** The picture, when the account has one; null means the initials are what shows. */
    public readonly ?string $imageUrl;

    /** Its first two letters, upper-cased -- what a picture-less account shows instead. */
    public readonly string $initials;

    /** `#rrggbb`, derived from the name so an account always gets the same one. */
    public readonly string $background;

    /** `#000000` or `#ffffff`, whichever the background reads better under. */
    public readonly string $foreground;

    public function __construct(
        string $name,
        ?string $imageUrl,
        string $initials,
        string $background,
        string $foreground
    ) {
        $this->name = $name;
        $this->imageUrl = $imageUrl;
        $this->initials = $initials;
        $this->background = $background;
        $this->foreground = $foreground;
    }

    public function hasImage(): bool
    {
        return $this->imageUrl !== null && $this->imageUrl !== '';
    }
}
