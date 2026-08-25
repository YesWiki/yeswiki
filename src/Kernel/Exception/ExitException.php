<?php

namespace YesWiki\Kernel\Exception;

/** `Redirector::redirect()` and `terminate()` unwinding: the request is over, and this is not an error. */
class ExitException extends \Exception
{
    /**
     * The ExitException inside a throwable, however deeply it was wrapped, or null.
     *
     * It is wrapped more often than not. An action that redirects is usually running inside a Twig
     * render, and Twig catches whatever a template throws and re-throws it as a `RuntimeError` with
     * the original as its previous. Anything that means to let a redirect through has to look past
     * the outermost class, or it treats the end of the request as a failure.
     */
    public static function in(\Throwable $throwable): ?self
    {
        for ($candidate = $throwable; $candidate !== null; $candidate = $candidate->getPrevious()) {
            if ($candidate instanceof self) {
                return $candidate;
            }
        }

        return null;
    }
}
