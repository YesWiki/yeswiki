<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Holder for the request being served (historic Wiki::$request). A holder rather
 * than a synthetic Request service because tests and a few flows replace the
 * request mid-run (Request::createFromGlobals() after mutating superglobals) and
 * every reader must observe the replacement.
 */
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
