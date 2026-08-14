<?php

namespace YesWiki\Content\Service;

/**
 * Reading a feed, which is four lines of SimplePie and one thing to know about it.
 *
 * The thing to know: the vendored SimplePie reads `$this->normalization[$this->scheme]`
 * with a **null** scheme in `IRI::scheme_normalization()`, which happens for every relative
 * link a feed carries -- and PHP 8.5 deprecates a null array offset, `isset()` included.
 * A notice per link, printed into the middle of whatever page was rendering the feed
 * (reported from korben.info's, a wall of them above the cards).
 *
 * It is third-party code we do not maintain and no release fixes it (1.9.0 is the latest),
 * so E_DEPRECATED is taken out of `error_reporting` while SimplePie works -- and nothing
 * else is, and only then.
 *
 * Two shapes of this were wrong before this one, both plausible:
 *
 *  - a `set_error_handler` around `init()`: SimplePie installs handlers of its own while it
 *    parses (Locator, Sanitize), and a notice raised inside one of those windows never
 *    reaches ours. Sixteen of them got through, measured.
 *  - the level masked after the constructor: `set_feed_url()` parses the url into an IRI,
 *    so the first notices are out before the feed has been fetched at all.
 *
 * Hence the level rather than a handler, held from before the constructor until after the
 * last thing the caller reads off the feed -- which is why the caller passes a callback
 * instead of being handed a feed to walk on its own. `get_permalink()` builds an IRI too.
 */
class FeedLoader
{
    /**
     * Read the feed at this url, inside the guard.
     *
     * @template T
     *
     * @param callable(\SimplePie\SimplePie): T $read what to take from the feed. Receives it
     *                                                initialised, error or not: only the
     *                                                caller knows whether a feed that will
     *                                                not load is an error or one absent
     *                                                item of a list
     *
     * @return T|null null when the url says nothing at all
     */
    public function read(string $url, callable $read, bool $cache = true): mixed
    {
        if (trim($url) === '') {
            return null;
        }

        $reporting = error_reporting();
        error_reporting($reporting & ~E_DEPRECATED);

        try {
            $feed = new \SimplePie\SimplePie();
            $feed->set_feed_url($url);
            $feed->enable_cache($cache);
            $feed->init();
            $feed->handle_content_type();

            return $read($feed);
        } finally {
            error_reporting($reporting);
        }
    }
}
