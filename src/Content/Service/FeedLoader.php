<?php

namespace YesWiki\Content\Service;

use YesWiki\Kernel\Service\SsrfUrlValidator;

/** Reading a feed, which is four lines of SimplePie and one thing to know about it. */
class FeedLoader
{
    private SsrfUrlValidator $ssrfUrlValidator;

    public function __construct(SsrfUrlValidator $ssrfUrlValidator)
    {
        $this->ssrfUrlValidator = $ssrfUrlValidator;
    }

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
     * @return T|null null when the url says nothing at all, a refused address included: SafeFile
     *                throws out of init() rather than returning an error on the feed, and an
     *                address the wiki may not reach is one more feed that has nothing to say
     */
    public function read(string $url, callable $read, bool $cache = true): mixed
    {
        if (trim($url) === '') {
            return null;
        }

        $reporting = error_reporting();
        error_reporting($reporting & ~E_DEPRECATED);

        try {
            SafeFile::$validator = $this->ssrfUrlValidator;
            $feed = new \SimplePie\SimplePie();
            $feed->get_registry()->register(\SimplePie\File::class, SafeFile::class);
            $feed->set_feed_url($url);
            $feed->enable_cache($cache);
            try {
                $feed->init();
            } catch (\Throwable $refused) {
                return null;
            }
            $feed->handle_content_type();

            return $read($feed);
        } finally {
            error_reporting($reporting);
        }
    }
}
