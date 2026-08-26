<?php

namespace YesWiki\Identity\Entity;

/** How an account shows its face: a picture, or a coloured disc with its initials on it. */
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

    /** A signed-out visitor: the name is a client IP. */
    public readonly bool $anonymous;

    /** Nobody at all: the console wrote this. */
    public readonly bool $system;

    public function __construct(
        string $name,
        ?string $imageUrl,
        string $initials,
        string $background,
        string $foreground,
        bool $anonymous = false,
        bool $system = false
    ) {
        $this->name = $name;
        $this->imageUrl = $imageUrl;
        $this->initials = $initials;
        $this->background = $background;
        $this->foreground = $foreground;
        $this->anonymous = $anonymous;
        $this->system = $system;
    }

    public function hasImage(): bool
    {
        return $this->imageUrl !== null && $this->imageUrl !== '';
    }

    /** No account stands behind this face. */
    public function isNobody(): bool
    {
        return $this->anonymous || $this->system;
    }
}
