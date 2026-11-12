<?php

namespace YesWiki\Kernel\Routing;

/**
 * Which page and method a request's address names: the one reading of it, used to dispatch and to decide anything that depends on what will be dispatched.
 * A non-empty `wiki` parameter takes precedence over everything else in the address.
 */
final class RequestedRoute
{
    private function __construct(
        public readonly string $page,
        public readonly string $method,
        public readonly bool $isValid,
    ) {
    }

    /**
     * @param ?string $wikiParameter the `wiki` query parameter, when there is one
     *
     * @return ?self null when the address names nothing
     */
    public static function parse(string $requestUri, string $scriptName, ?string $wikiParameter): ?self
    {
        $scriptLocation = str_replace('/index.php', '', $scriptName);
        $uri = preg_replace('~^/\??~', '', str_replace($scriptLocation, '', $requestUri)) ?? '';
        $uri = explode('&', $uri);
        $uri = explode('?', $uri[0]);
        $args = explode('/', rawurldecode($uri[0]));
        if (empty($args[0]) && empty($wikiParameter)) {
            return null;
        }
        if ($args[0] == 'index.php' || $args[0] == 'worker.php' || !empty($wikiParameter)) {
            $wiki = empty($wikiParameter) ? '' : preg_replace('/^\//', '', urldecode($wikiParameter));
        } else {
            $wiki = urldecode(explode('=', $args[0])[0]);
        }

        if (empty($wiki)) {
            return new self('', '', true);
        }
        if (ReservedTags::isReserved(explode('/', $wiki)[0])) {
            $parts = strpos($wiki, '/') !== false ? explode('/', $wiki) : $args;
            array_shift($parts);

            return new self(ReservedTags::canonical(explode('/', $wiki)[0]), rtrim(implode('/', $parts), '='), true);
        }
        if (preg_match('`^' . WN_TAG_HANDLER_CAPTURE . '$`u', $wiki, $matches)) {
            return new self($matches[1], $matches[2], true);
        }
        if (preg_match('`^' . WN_PAGE_TAG . '$`u', $wiki)) {
            $method = !empty($args[1]) && preg_match('#^[A-Za-z0-9_]*$#', $args[1]) ? $args[1] : '';

            return new self($wiki, $method, true);
        }

        return new self('', '', false);
    }

    /** What the router is given. */
    public function address(): string
    {
        return $this->page . ($this->method ? '/' . $this->method : '');
    }
}
