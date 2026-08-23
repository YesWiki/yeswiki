<?php

namespace YesWiki\Content\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/** Pages fetched from another wiki while serving this one, kept for the length of the request. */
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
