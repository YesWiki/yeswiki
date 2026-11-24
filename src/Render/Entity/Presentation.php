<?php

namespace YesWiki\Render\Entity;

/** One list template as its header declares it: what to call it, where it sits in the switcher, and what a form must have for it to work. */
final class Presentation
{
    /** The switcher's five families, each with its icon. */
    public const CATEGORIES = [
        'card' => 'layout-grid',
        'list' => 'layout-list',
        'table' => 'table',
        'map' => 'map-2',
        'calendar' => 'calendar',
    ];

    public const DEFAULT_CATEGORY = 'list';

    /**
     * @param list<string> $requires field roles a form must answer
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $icon,
        public readonly string $category,
        public readonly array $requires = [],
        public readonly bool $shared = false,
        public readonly bool $custom = false,
    ) {
    }

    public static function categoryIcon(string $category): string
    {
        return self::CATEGORIES[$category] ?? self::CATEGORIES[self::DEFAULT_CATEGORY];
    }

    public static function categoryLabel(string $category): string
    {
        return _t('PRESENTATION_' . strtoupper($category));
    }

    /** @return array{name: string, label: string, icon: string, category: string, requires: list<string>, shared: bool, custom: bool} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'icon' => $this->icon,
            'category' => $this->category,
            'requires' => $this->requires,
            'shared' => $this->shared,
            'custom' => $this->custom,
        ];
    }
}
