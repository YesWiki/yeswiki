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
}
