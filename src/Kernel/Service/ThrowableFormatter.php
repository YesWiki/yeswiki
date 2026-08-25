<?php

namespace YesWiki\Kernel\Service;

/**
 * Render a throwable for display without leaking the server's filesystem layout (historic Wiki::dumpThrowable()/hideServerPath()).
 */
class ThrowableFormatter
{
    /** How many frames of a trace are worth keeping. */
    public const FRAME_LIMIT = 20;

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

    /**
     * The trace with every argument replaced by its type, which is the only form of it the Journal and the log are allowed to carry (ADR-0025).
     *
     * Safe by construction: a value never reaches the string, so there is no pattern list
     * deciding which ones were secrets. `$password` and `$p` and a whole `$_POST` arriving as one
     * `$data` argument are all equally covered, which is the shape that leaked in every
     * well-known incident.
     *
     * @return list<string> e.g. `FormManager::save(string, array<7>, bool) at src/Content/Service/FormManager.php:782`
     */
    public function frames(\Throwable $throwable): array
    {
        $frames = [];
        foreach (array_slice($throwable->getTrace(), 0, self::FRAME_LIMIT) as $frame) {
            $call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];
            $call .= '(' . implode(', ', array_map(
                fn (mixed $argument): string => $this->typeOf($argument),
                $frame['args'] ?? []
            )) . ')';

            if (isset($frame['file'])) {
                $call .= ' at ' . ltrim($this->hideServerPath((string)$frame['file']), '/') . ':' . ($frame['line'] ?? 0);
            }

            $frames[] = $call;
        }

        return $frames;
    }

    /** What an argument was, never what it held. */
    private function typeOf(mixed $argument): string
    {
        if (is_object($argument)) {
            return $argument::class;
        }
        if (is_array($argument)) {
            return 'array<' . count($argument) . '>';
        }
        if (is_resource($argument)) {
            return 'resource';
        }

        return get_debug_type($argument);
    }
}
