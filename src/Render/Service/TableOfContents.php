<?php

namespace YesWiki\Render\Service;

/** The one table of contents of the wiki: `{{toc}}` in a page and the documentation's side column both draw it here, as a folding box or as a rail that follows the reading. */
class TableOfContents
{
    public const DISPLAYS = ['block', 'rail', 'graduated-rail'];

    public const FOLDING_FROM = 13;

    public function __construct(private readonly TemplateEngine $templates)
    {
    }

    /** @param list<array{level: int, id: string, title: string}> $headings the headings to list, in the order of the page; $name tells this table's ids apart from another's */
    public function render(array $headings, string $name, string $display = 'block', string $title = '', bool $showTitle = false, string $class = '', bool $closed = false): string
    {
        $display = in_array($display, self::DISPLAYS, true) ? $display : 'block';
        if ($display !== 'block' && $headings === []) {
            return '';
        }
        $top = $headings === [] ? 0 : min(array_column($headings, 'level'));
        $items = [];
        $parents = false;
        foreach ($headings as $index => $heading) {
            $children = $heading['level'] === $top && ($headings[$index + 1]['level'] ?? $top) > $top;
            $parents = $parents || $children;
            $items[] = $heading + ['section' => $heading['level'] === $top, 'children' => $children];
        }

        return $this->templates->render('@core/toc.twig', [
            'items' => $items,
            'id' => 'toc' . $name,
            'menu_id' => 'toc-menu' . $name,
            'display' => $display,
            'title' => $title,
            'show_title' => $showTitle,
            'class' => $class,
            'closed' => $closed,
            'foldable' => $display === 'rail' && $parents && count($items) >= self::FOLDING_FROM,
        ]);
    }
}
