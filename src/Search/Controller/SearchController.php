<?php

namespace YesWiki\Search\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `/search` (ticket 26).
 *
 * Thin on purpose: it runs the `{{search}}` action and wraps the result in the wiki's page
 * skeleton. Every decision about what search *is* lives in the action, so a webmaster who
 * embeds `{{search}}` in a page of their own gets exactly what this route gets.
 *
 * Registering this route reserves the tag `search` automatically -- `ReservedTags::NAMES` is
 * derived from the real route table and guarded by `ReservedTagsTest` (ticket 20), so there
 * is no list to remember to edit. Content already sitting on that tag is moved off it by
 * migration 20260802140000, because the route wins and a shadowed page has no URL at all.
 */
class SearchController extends YesWikiController
{
    #[Route('/search', methods: ['GET'], options: ['acl' => ['public']])]
    public function show(): Response
    {
        // autofocus is the route's to ask for, not the action's to assume: on /search the
        // box is the page, while an embedded {{search}} must leave the reader's focus alone
        $content = $this->getService(ActionRunner::class)->action('search', ['autofocus' => '1']);

        return new Response(
            $this->getService(TemplateEngine::class)->renderPage('<div class="page">' . $content . '</div>')
        );
    }
}
