<?php

namespace YesWiki\Render\Component;

/**
 * A list's `query` parameter as a tree of AND/OR groups and `field=value` conditions, which the settings rail edits;
 * the older `a=1,2|b=3` syntax is read as the tree it stands for.
 */
final class QueryTree
{
    public const OPERATORS = ['=', '!=', '<', '>', '<=', '>='];
    public const EMPTY = ['connector' => 'AND', 'children' => []];
    public const CONDITION = ['field' => '', 'operator' => '=', 'value' => ''];
    public const GROUP = ['connector' => 'AND', 'children' => [self::CONDITION]];

    /**
     * The tree a query writes, or null when it is not one the tree can hold, and is then edited as text.
     *
     * @return array<string, mixed>|null
     */
    public static function read(string $query): ?array
    {
        $query = self::fromLegacy($query);
        if ($query === '') {
            return self::EMPTY;
        }
        $tokens = self::tokens($query);
        if ($tokens === null) {
            return null;
        }
        $position = 0;
        $node = self::parseOr($tokens, $position);
        if ($node === null || $position !== count($tokens)) {
            return null;
        }

        return isset($node['children']) ? $node : ['connector' => 'AND', 'children' => [$node]];
    }

    /** @param array<string, mixed> $root */
    public static function write(array $root): string
    {
        $parts = array_values(array_filter(array_map([self::class, 'writeChild'], $root['children'] ?? []), static fn ($p) => $p !== ''));

        return implode(' ' . ($root['connector'] ?? 'AND') . ' ', $parts);
    }

    /**
     * The tree with a condition or a group added to the group at $path.
     *
     * @param array<string, mixed> $tree
     * @param list<string>         $path
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    public static function add(array $tree, array $path, array $node): array
    {
        if ($path === []) {
            $tree['children'][] = $node;

            return $tree;
        }
        $index = (int)array_shift($path);
        if (isset($tree['children'][$index]['children'])) {
            $tree['children'][$index] = self::add($tree['children'][$index], $path, $node);
        }

        return $tree;
    }

    /**
     * The tree without the child at $path.
     *
     * @param array<string, mixed> $tree
     * @param list<string>         $path
     *
     * @return array<string, mixed>
     */
    public static function remove(array $tree, array $path): array
    {
        $index = (int)array_shift($path);
        if ($path === []) {
            array_splice($tree['children'], $index, 1);
        } elseif (isset($tree['children'][$index]['children'])) {
            $tree['children'][$index] = self::remove($tree['children'][$index], $path);
        }

        return $tree;
    }

    /** `a=1,2|b!=3` as `(a=1 OR a=2) AND b!=3`; a query already written the new way is left as it is. */
    public static function fromLegacy(string $query): string
    {
        $query = trim($query);
        if ($query === '' || preg_match('/ (AND|OR) /', $query) === 1 || str_contains($query, '(')) {
            return $query;
        }
        $fragments = [];
        foreach (explode('|', $query) as $fragment) {
            if (trim($fragment) === '') {
                continue;
            }
            if (preg_match('/^\s*([^=!<>]*?)\s*(==|!=|<=|>=|=|<|>)([\s\S]*)$/', $fragment, $match) !== 1) {
                $fragments[] = trim($fragment);
                continue;
            }
            [, $name, $operator, $rest] = $match;
            $values = array_map('trim', explode(',', trim($rest)));
            if (count($values) <= 1) {
                $fragments[] = trim($name) . $operator . trim($rest);
                continue;
            }
            $glue = $operator === '!=' ? ' AND ' : ' OR ';
            $fragments[] = '(' . implode($glue, array_map(static fn ($value) => trim($name) . $operator . $value, $values)) . ')';
        }

        return implode(' AND ', $fragments);
    }

    /** @param array<string, mixed> $node */
    private static function writeChild(array $node): string
    {
        if (isset($node['children'])) {
            $parts = array_values(array_filter(array_map([self::class, 'writeChild'], $node['children']), static fn ($p) => $p !== ''));
            if (count($parts) <= 1) {
                return $parts[0] ?? '';
            }

            return '(' . implode(' ' . $node['connector'] . ' ', $parts) . ')';
        }

        return ($node['field'] ?? '') === '' ? '' : $node['field'] . $node['operator'] . ($node['value'] ?? '');
    }

    /** @return list<array{type: string, value?: string}>|null */
    private static function tokens(string $query): ?array
    {
        $tokens = [];
        $buffer = '';
        $depth = 0;
        $flush = static function () use (&$buffer, &$tokens): void {
            if (trim($buffer) !== '') {
                $tokens[] = ['type' => 'leaf', 'value' => trim($buffer)];
            }
            $buffer = '';
        };
        $length = strlen($query);
        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];
            if ($char === '[' || $char === ']') {
                $depth = max(0, $depth + ($char === '[' ? 1 : -1));
                $buffer .= $char;
                continue;
            }
            if ($depth === 0) {
                if ($char === '|' || $char === ',') {
                    return null;
                }
                if ($char === '(' || $char === ')') {
                    $flush();
                    $tokens[] = ['type' => $char];
                    continue;
                }
                foreach (['AND', 'OR'] as $word) {
                    if (substr($query, $i, strlen($word) + 2) === ' ' . $word . ' ') {
                        $flush();
                        $tokens[] = ['type' => $word];
                        $i += strlen($word);
                        continue 2;
                    }
                }
            }
            $buffer .= $char;
        }
        $flush();

        return $tokens;
    }

    /**
     * @param list<array{type: string, value?: string}> $tokens
     *
     * @return array<string, mixed>|null
     */
    private static function parseOr(array $tokens, int &$position): ?array
    {
        return self::parseJoined($tokens, $position, 'OR', [self::class, 'parseAnd']);
    }

    /**
     * @param list<array{type: string, value?: string}> $tokens
     *
     * @return array<string, mixed>|null
     */
    private static function parseAnd(array $tokens, int &$position): ?array
    {
        return self::parseJoined($tokens, $position, 'AND', [self::class, 'parseTerm']);
    }

    /**
     * @param list<array{type: string, value?: string}> $tokens
     *
     * @return array<string, mixed>|null
     */
    private static function parseJoined(array $tokens, int &$position, string $connector, callable $next): ?array
    {
        $nodes = [$next($tokens, $position)];
        while (end($nodes) !== null && ($tokens[$position]['type'] ?? null) === $connector) {
            $position++;
            $nodes[] = $next($tokens, $position);
        }
        if (in_array(null, $nodes, true)) {
            return null;
        }

        return count($nodes) === 1 ? $nodes[0] : ['connector' => $connector, 'children' => $nodes];
    }

    /**
     * @param list<array{type: string, value?: string}> $tokens
     *
     * @return array<string, mixed>|null
     */
    private static function parseTerm(array $tokens, int &$position): ?array
    {
        $token = $tokens[$position] ?? null;
        if ($token === null) {
            return null;
        }
        if ($token['type'] === '(') {
            $position++;
            $node = self::parseOr($tokens, $position);
            if ($node === null || ($tokens[$position]['type'] ?? null) !== ')') {
                return null;
            }
            $position++;

            return $node;
        }
        if ($token['type'] !== 'leaf') {
            return null;
        }
        $position++;
        if (preg_match('/^\s*([^=!<>]*?)\s*(==|!=|<=|>=|=|<|>)(.*)$/', (string)($token['value'] ?? ''), $match) !== 1) {
            return null;
        }

        return ['field' => trim($match[1]), 'operator' => $match[2] === '==' ? '=' : $match[2], 'value' => trim($match[3])];
    }
}
