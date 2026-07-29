<?php

namespace YesWiki\Kernel\Service;

/**
 * Render a throwable for display without leaking the server's filesystem layout
 * (historic Wiki::dumpThrowable()/hideServerPath()).
 */
class ThrowableFormatter
{
    public function dump(\Throwable $throwable): string
    {
        return htmlspecialchars($this->hideServerPath($throwable->getMessage()))
            . ' in <i>.' . htmlspecialchars($this->hideServerPath($throwable->getFile())) . '</i>'
            . ' on line <i>' . $throwable->getLine() . '</i>';
    }

    /** Strip the repository root out of a path or message. */
    public function hideServerPath(string $path): string
    {
        $rootPath = (string)realpath(__DIR__ . '/../../..');

        return str_replace($rootPath, '', $path);
    }
}
