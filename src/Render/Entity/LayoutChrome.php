<?php

namespace YesWiki\Render\Entity;

/** The wiki's chrome as a value: a title, a logo, two menus, and how tall the bar is. */
final class LayoutChrome
{
    /**
     * @param list<array{label: string, link: string, children: list<array{label: string, link: string}>}> $navbar
     * @param list<array{icon: string, label: string, link: string}>                                       $quickMenu
     */
    public function __construct(
        public readonly string $title,
        public readonly string $logo,
        public readonly string $brandMode,
        public readonly array $navbar,
        public readonly array $quickMenu,
        public readonly bool $accountButton,
        public readonly int $navbarHeight,
    ) {
    }
}
