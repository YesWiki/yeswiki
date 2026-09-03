<?php

namespace YesWiki\Render\Service;

use YesWiki\Content\Entity\MenuNode;
use YesWiki\Content\Service\MenuManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * The one thing that draws a menu, wherever it is drawn (ticket 64 / ADR-0028).
 *
 * There used to be three: the navbar template drew two levels and no icons, the quick access bar
 * drew icons and one level, and `{{nav}}` concatenated strings and decided "active" by comparing
 * built URLs while the navbar compared bare tags. An icon worked in one placement, a dropdown in
 * another, and a link written as `Tag/edit` never lit up. Everything a placement may still differ
 * about is a flag it passes in.
 */
class MenuRenderer
{
    /** Where a menu is being drawn: the wrapper differs, nothing else does. */
    public const NAVBAR = 'navbar';
    public const QUICK = 'quick';
    public const NAV = 'nav';

    public function __construct(
        private readonly MenuManager $menuManager,
        private readonly PageManager $pageManager,
        private readonly AclService $aclService,
        private readonly UrlFormatter $urlFormatter,
        private readonly PageContext $pageContext,
        private readonly TemplateEngine $templateEngine,
    ) {
    }

    /**
     * @param array{showicons?: bool, showlabels?: bool, showdropdown?: bool, class?: string, data?: array<string, string>, appended?: string} $options
     */
    public function render(string $menuTag, string $placement = self::NAV, array $options = []): string
    {
        $menu = $menuTag === '' ? null : $this->menuManager->getOne($menuTag);

        return $this->renderNodes($menu === null ? [] : $menu['nodes'], $placement, $options);
    }

    /**
     * The same drawing, from nodes rather than from a tag.
     *
     * The Layout screen previews chrome that has not been saved yet, so there is no row to read it
     * back from -- the entries being typed are what it has (ticket 30's preview, ticket 64's menus).
     *
     * @param list<MenuNode>                                                                                                                         $nodes
     * @param array{showicons?: bool, showlabels?: bool, showdropdown?: bool, class?: string, data?: array<string, string>, appended?: string} $options
     */
    public function renderNodes(array $nodes, string $placement = self::NAV, array $options = []): string
    {
        $entries = $this->entries($nodes, (bool)($options['showdropdown'] ?? true));

        if ($entries === [] && ($options['appended'] ?? '') === '') {
            return '';
        }

        return $this->templateEngine->render('@core/layout/menu.twig', [
            'entries' => $entries,
            'placement' => $placement,
            'showIcons' => (bool)($options['showicons'] ?? false),
            'showLabels' => (bool)($options['showlabels'] ?? true),
            'class' => (string)($options['class'] ?? ''),
            'dataAttributes' => $options['data'] ?? [],
            'appended' => $options['appended'] ?? '',
        ]);
    }

    /**
     * The nodes a visitor may see, each one told where it goes and whether it is the page they are on.
     *
     * @param list<MenuNode> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function entries(array $nodes, bool $withChildren): array
    {
        $entries = [];
        foreach ($nodes as $node) {
            $entry = $this->entry($node);
            if ($entry === null) {
                continue;
            }

            $children = [];
            if ($withChildren) {
                foreach ($node->children as $child) {
                    $childEntry = $this->entry($child);
                    if ($childEntry !== null) {
                        $children[] = $childEntry;
                    }
                }
            }
            $entry['children'] = $children;
            $entry['active'] = $entry['active'] || $children !== [] && array_filter(array_column($children, 'active')) !== [];

            // A parent that leads nowhere and has nothing left under it says nothing at all.
            if ($entry['href'] === '' && $children === []) {
                continue;
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>|null null when this visitor is not shown this entry at all
     */
    private function entry(MenuNode $node): ?array
    {
        $parts = $node->link === '' ? null : ($this->urlFormatter->extractLinkParts($node->link) ?: null);
        $missing = false;

        if ($parts !== null) {
            $tag = (string)($parts['tag'] ?? '');
            $method = (string)($parts['method'] ?? '');

            // Two rules, everywhere, with no setting: what you may not read you are not shown, and
            // what does not exist yet is an invitation for whoever may create it (ADR-0028).
            //
            // A reserved name is neither: `search` and `dashboard` belong to the router, so there
            // is no row to find and nothing to invite anybody to write (ticket 20).
            $routed = ReservedTags::isReserved($tag);
            if (!$routed && !$this->aclService->hasAccess('read', $tag)) {
                return null;
            }
            if (!$routed && ($method === '' || $method === 'show') && $this->pageManager->getOne($tag) === null) {
                if (!$this->aclService->hasAccess('write', $tag)) {
                    return null;
                }
                $missing = true;
            }

            $href = $this->urlFormatter->href($missing ? 'edit' : $method, $tag, $parts['params'], false);
            $active = $tag === $this->pageContext->getTag();
        } else {
            $href = $node->link;
            $active = $href !== '' && $href === $this->urlFormatter->href('', $this->pageContext->getTag(), null, false);
        }

        return [
            'label' => $node->label,
            'href' => $href,
            'active' => $active,
            'missing' => $missing,
            'icon' => $this->icon($node),
            'children' => [],
        ];
    }

    /** The node's glyph as HTML, or null when it has none this wiki can draw. */
    private function icon(MenuNode $node): ?string
    {
        return $node->hasIcon()
            ? $this->templateEngine->renderNodeIcon((string)$node->iconSource, $node->iconValue)
            : null;
    }
}
