<?php

namespace YesWiki\Content\Service;

/** Reading a feed, which is four lines of SimplePie and one thing to know about it. */
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
