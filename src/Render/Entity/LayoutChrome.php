<?php

namespace YesWiki\Render\Entity;

use YesWiki\Content\Entity\MenuNode;

/**
 * The wiki's chrome as a value: a title, a logo, two menus, and how tall the bar is.
 *
 * The menus arrive as nodes rather than as the tags configuration names them by, because the Layout
 * screen previews what is being typed and there is no row to read that back from (ticket 64).
 */
final class LayoutChrome
{
    /**
     * @param list<MenuNode>                                                    $navbar
     * @param list<MenuNode>                                                    $quickMenu
     * @param array{showicons: bool, showlabels: bool, showdropdown: bool}      $navbarFlags
     * @param array{showicons: bool, showlabels: bool, showdropdown: bool}      $quickMenuFlags
     */
    public function __construct(
        public readonly string $title,
        public readonly string $logo,
        public readonly string $brandMode,
        public readonly array $navbar,
        public readonly array $quickMenu,
        public readonly array $navbarFlags,
        public readonly array $quickMenuFlags,
        public readonly bool $accountButton,
        public readonly int $navbarHeight,
    ) {
    }
}
