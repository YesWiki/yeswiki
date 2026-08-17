<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Kernel\Exception\ExitException;

/**
 * Request interruption (historic Wiki::Redirect()/Wiki::exit()): always throws ExitException rather than calling PHP's exit(), so kernel.response/terminate still run when dispatch goes through the HttpKernel; the top-level dispatch loop decides what to do with it per entry point.
 */
class Redirector
{
    public function redirect(string $url): never
    {
        header("Location: $url");
        $this->terminate();
    }

    /** End the request, optionally emitting $message (historic Wiki::exit()). */
    public function terminate(string $message = ''): never
    {
        throw new ExitException($message);
    }
}
