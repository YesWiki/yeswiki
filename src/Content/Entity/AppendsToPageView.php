<?php

namespace YesWiki\Content\Entity;

/** Adds its own block to the bottom of a rendered page. */
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
