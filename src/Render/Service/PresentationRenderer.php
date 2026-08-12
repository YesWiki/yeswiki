<?php

namespace YesWiki\Render\Service;

use YesWiki\Content\Entity\Item;

/**
 * Renders a list of Items in one of the shared shapes.
 *
 * This is the half of ticket 37 that makes `template="card"` mean one thing: whatever
 * supplied the list -- a form's entries, an RSS feed, a page index -- ends up here, and
 * ends up looking the same.
 *
 * Server-rendered, deliberately. `{{entrylist dynamic="true"}}` has a Vue renderer that
 * pages and filters by calling `api/entries`, and it keeps it; but a feed has no such
 * endpoint, so a Presentation shared with syndication could only ever be server-rendered.
 * It is the better default anyway -- a card list that needs JavaScript to appear is a card
 * list a reader may never see.
 */
class PresentationRenderer
{
    /**
     * The shapes a list can take, and nothing else: a template name reaches a filesystem
     * path, so only these do.
     */
    public const PRESENTATIONS = ['card', 'list', 'table', 'timeline'];

    public function __construct(private readonly TemplateEngine $twig)
    {
    }

    public static function knows(string $template): bool
    {
        return in_array(self::bare($template), self::PRESENTATIONS, true);
    }

    /**
     * @param list<Item>           $items
     * @param array<string, mixed> $params the Source's own arguments, for the settings a
     *                                     presentation reads (columns, style, imgstyle…)
     */
    public function render(string $template, array $items, array $params = []): string
    {
        $name = self::bare($template);
        if (!in_array($name, self::PRESENTATIONS, true)) {
            $name = 'list';
        }

        return $this->twig->render("@core/presentations/{$name}.twig", [
            'items' => array_map(static fn (Item $item) => $item->toArray(), $items),
            'params' => $params,
        ]);
    }

    /**
     * The same template is written `card` in page content and `card.twig` in a config, and
     * `tableau.tpl.html` in bodies old enough to predate the Twig move -- all three name one
     * presentation.
     */
    private static function bare(string $template): string
    {
        return (string)preg_replace('/\.(twig|tpl\.html)$/', '', $template);
    }
}
