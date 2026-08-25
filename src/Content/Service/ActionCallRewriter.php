<?php

namespace YesWiki\Content\Service;

use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Files\Service\Storage;

/**
 * Rewrites `{{action param="..."}}` calls in stored wiki syntax from the French names to the English ones (tickets 22 and 23, migrated by ticket 33).
 */
class ActionCallRewriter
{
    /**
     * old action name (lowercase) => new action name.
     *
     * @var array<string, string>
     */
    private array $actionRenames;

    /**
     * old action name (lowercase) => [old parameter key (lowercase) => new parameter key].
     *
     * @var array<string, array<string, string>>
     */
    private array $parameterRenames;

    public function __construct()
    {
        $this->actionRenames = self::loadActionRenames();
        $this->parameterRenames = self::loadParameterRenames();
    }

    /** Rewrite every action call in a piece of wiki syntax. */
    public function rewriteText(string $text): string
    {
        return (string)preg_replace_callback(
            '/\{\{(.*?)\}\}/s',
            fn (array $matches): string => '{{' . $this->rewriteCall($matches[1]) . '}}',
            $text
        );
    }

    /**
     * Rewrite every string value in a decoded page body.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null null when nothing in it changed, so the caller can skip the write
     */
    public function rewriteBody(array $body): ?array
    {
        $changed = false;
        array_walk_recursive($body, function (&$value) use (&$changed): void {
            if (!is_string($value) || !str_contains($value, '{{')) {
                return;
            }
            $rewritten = $this->rewriteText($value);
            if ($rewritten !== $value) {
                $value = $rewritten;
                $changed = true;
            }
        });

        return $changed ? $body : null;
    }

    /**
     * A SQL fragment matching rows that could possibly contain something to rewrite, for narrowing the sweep.
     *
     * @return list<string> the names to test with LIKE
     */
    public function candidateNeedles(): array
    {
        $needles = array_keys($this->actionRenames);
        foreach ($this->parameterRenames as $parameters) {
            foreach (array_keys($parameters) as $old) {
                $needles[] = $old;
            }
        }

        return array_values(array_unique($needles));
    }

    /**
     * @return array<string, string> old => new, for reporting and tests
     */
    public function actionRenames(): array
    {
        return $this->actionRenames;
    }

    /** Rewrite one call's interior -- what sits between the braces. */
    private function rewriteCall(string $inner): string
    {
        if (preg_match('/^(\s*)([a-zA-Z0-9_-]+)(\/?)(.*)$/s', $inner, $matches) !== 1) {
            return $inner;
        }
        [, $leading, $name, $slash, $arguments] = $matches;

        $key = strtolower($name);

        $arguments = $this->rewriteParameters($key, $arguments);

        return $leading . ($this->actionRenames[$key] ?? $name) . $slash . $arguments;
    }

    /** Rewrite the parameter keys of one call, leaving every value alone. */
    private function rewriteParameters(string $actionName, string $arguments): string
    {
        $renames = $this->parameterRenames[$actionName] ?? [];
        if ($renames === []) {
            return $arguments;
        }

        return (string)preg_replace_callback(
            '/([a-zA-Z0-9_]+)(\s*=\s*")([^"]*)(")/',
            function (array $matches) use ($renames): string {
                $new = $renames[strtolower($matches[1])] ?? $matches[1];

                return $new . $matches[2] . $matches[3] . $matches[4];
            },
            $arguments
        );
    }

    /**
     * @return array<string, string>
     */
    private static function loadActionRenames(): array
    {
        $map = [];
        foreach (self::readMap('action-name-renames.json') as $rename) {
            $map[strtolower((string)$rename['old'])] = (string)$rename['new'];
        }

        return $map;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function loadParameterRenames(): array
    {
        $map = [];
        foreach (self::readMap('action-parameter-renames.json') as $rename) {
            if (!isset($rename['action']) || empty($rename['userTyped'])) {
                continue;
            }
            $action = strtolower((string)$rename['action']);
            $map[$action][strtolower((string)$rename['old'])] = (string)$rename['new'];
        }

        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function readMap(string $file): array
    {
        // A shipped map, read out of the Program: `docs/action-name-renames.json` is part of the
        // release, not of any wiki. Static because the constructor loads it before there is an
        // instance to ask, so ProgramFiles is built here rather than injected -- it holds nothing.
        $path = 'docs/' . $file;
        $raw = (new ProgramFiles(new Storage()))->read($path);
        if ($raw === '') {
            throw new \RuntimeException("Cannot read the rename map {$path}. The action rename migration cannot run " . 'without it, and skipping it would leave stored content calling actions that no longer exist.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['renames']) || !is_array($decoded['renames'])) {
            throw new \RuntimeException("The rename map {$path} has no 'renames' array.");
        }

        return array_values($decoded['renames']);
    }
}
