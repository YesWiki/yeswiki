<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\HttpFoundation\Request;

/** Holder for the request being served (historic Wiki::$request). */
class CurrentRequest
{
    protected Request $request;

    public function replace(Request $request): void
    {
        $this->request = $request;
    }

    public function get(): Request
    {
        return $this->request;
    }

    /** Whether a request has been set: false during boot, and in a console command. */
    public function has(): bool
    {
        return isset($this->request);
    }

    /** A parameter from the route attributes, then the query string, then the posted body: what Request::get() did before Symfony deprecated it. */
    public static function input(Request $request, string $key, mixed $default = null): mixed
    {
        if ($request->attributes->has($key)) {
            return $request->attributes->get($key);
        }
        if ($request->query->has($key)) {
            return $request->query->all()[$key];
        }
        if ($request->request->has($key)) {
            return $request->request->all()[$key];
        }

        return $default;
    }
}
