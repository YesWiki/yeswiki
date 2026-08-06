<?php

namespace YesWiki\Render\Entity;

/**
 * The wiki's chrome as a value: a title, a logo, two menus, and how tall the bar is.
 *
 * It exists so that the chrome can be *rendered from something other than the configuration*
 * -- which is the whole of the live preview on `/admin/layout`. The alternative was a setter
 * on LayoutService, and a service carrying per-request state is the bug recorded in ticket
 * 24: two `{{action}}` tags on one page, and the second renders the first one's data.
 *
 * So there are two ways to get one: `LayoutService::current()` reads the config, and
 * `LayoutService::fromForm()` reads a posted form. Everything downstream -- the squelette,
 * the preview endpoint, the save -- takes this and cannot tell the two apart, which is why
 * the preview is a real preview rather than an approximation of one.
 */
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
