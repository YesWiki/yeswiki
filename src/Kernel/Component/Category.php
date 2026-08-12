<?php

namespace YesWiki\Kernel\Component;

/**
 * The palette's sections, in the order a webmaster meets them.
 *
 * Core declares them once, here, and a Component names the one it belongs to. This replaces
 * the `position` integer each YAML group carried: spread across sixteen files those had
 * produced 2, 3, 4, 1000 seven times over, 1997, 1998, 1999 and 2000, and an extension
 * author had no way of knowing what number to pick. "Most useful for page editing" is an
 * editorial judgement, and an editorial judgement belongs in one ordered list rather than
 * in a negotiation between files.
 *
 * Declaration order IS palette order. `Other` is last and is where a Component that names
 * no category lands -- an extension is never blocked from appearing for want of a category.
 */
enum Category: string
{
    /** Structure and emphasis for prose: the things you reach for while writing. */
    case Writing = 'writing';

    /** Pictures, files, anything embedded. */
    case Media = 'media';

    /** The Presentations -- what a list of things looks like. */
    case Lists = 'lists';

    /** Getting somewhere else. */
    case Navigation = 'navigation';

    /** Asking a visitor for something, or showing what they said. */
    case Forms = 'forms';

    /** Running the wiki rather than writing a page. Usually `adminOnly()` as well. */
    case Admin = 'admin';

    /** Anything that named no category. Last, always. */
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Writing => _t('COMPONENT_CATEGORY_WRITING'),
            self::Media => _t('COMPONENT_CATEGORY_MEDIA'),
            self::Lists => _t('COMPONENT_CATEGORY_LISTS'),
            self::Navigation => _t('COMPONENT_CATEGORY_NAVIGATION'),
            self::Forms => _t('COMPONENT_CATEGORY_FORMS'),
            self::Admin => _t('COMPONENT_CATEGORY_ADMIN'),
            self::Other => _t('COMPONENT_CATEGORY_OTHER'),
        };
    }

    /** Where this sits in the palette. Lower first; derived from declaration order. */
    public function position(): int
    {
        foreach (self::cases() as $index => $case) {
            if ($case === $this) {
                return $index;
            }
        }

        // unreachable: $this is one of the cases being walked
        return count(self::cases());
    }
}
