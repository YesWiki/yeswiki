<?php

namespace YesWiki\Kernel\Component;

/** The palette's sections, in the order a webmaster meets them. */
enum Category: string
{
    case Writing = 'writing';

    case Media = 'media';

    case Lists = 'lists';

    case Navigation = 'navigation';

    case Forms = 'forms';

    case Admin = 'admin';

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

    /** Where this sits in the palette. */
    public function position(): int
    {
        foreach (self::cases() as $index => $case) {
            if ($case === $this) {
                return $index;
            }
        }

        return count(self::cases());
    }
}
