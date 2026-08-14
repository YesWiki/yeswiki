<?php

namespace YesWiki\Content\Entity;

/**
 * Adds its own block to the bottom of a rendered page.
 *
 * `ShowHandler` used to end by calling `CommentService::renderCommentsForPage()`, which is the
 * page view depending on the comment feature. It asks every contributor instead, and `Social`
 * is one -- the same inversion as `ContributesEntryFields`, for HTML rather than for data
 * (ADR-0019).
 *
 * Discovered by the `yeswiki.page_view_appendix` DI tag declared against this interface.
 *
 * Order is the container's, which is declaration order, and nothing today has an opinion about
 * it. The day something does, this needs a priority rather than a convention.
 */
interface AppendsToPageView
{
    /**
     * @param string $tag the page being shown
     *
     * @return string HTML appended after the page body, or '' to add nothing -- returning ''
     *                is the normal way to decline, e.g. comments turned off for this page
     */
    public function appendToPageView(string $tag): string;
}
