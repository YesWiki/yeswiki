<?php

namespace YesWiki\Content\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/**
 * Pages fetched from another wiki while serving this one, kept for the length of the request.
 *
 * `{{value}}` reads one field out of a remote page, and a page that shows six fields of the same
 * remote entry would otherwise fetch it six times. The memo is worth having and its lifetime is
 * exactly one render.
 *
 * It was `$GLOBALS['externalpage']`, which under worker mode (ADR-0024) turns a within-render memo
 * into a cache with no expiry and no size limit: a reader gets whatever the other wiki said the
 * first time anybody asked, for as long as the process lives.
 */
class RemotePageCache implements RequestScopedState
{
    /** @var array<string, string|false> url => what it answered, false when it could not be read */
    private array $fetched = [];

    /**
     * The page at $url, fetched once per request.
     *
     * @param callable(): (string|false) $fetch performs the request, which only the caller knows how to do
     *
     * @return string|false
     */
    public function get(string $url, callable $fetch)
    {
        if (!array_key_exists($url, $this->fetched)) {
            $this->fetched[$url] = $fetch();
        }

        return $this->fetched[$url];
    }

    public function startNewRequest(): void
    {
        $this->fetched = [];
    }
}
