<?php

namespace YesWiki\Render\Twig;

/** Compiles `{{name attr="value"}}`, the wiki's action syntax, into the Twig call it means, leaving every Twig construct alone. */
final class WikiActionSyntax
{
    private const CALL = '/\{\{([a-zA-Z0-9_-]+)((?:\s+[a-zA-Z0-9_-]+="[^"]*")*)\s*(\/?)\}\}/A';
    private const RAW_BLOCK = '/\{%[-~]?\s*(verbatim|raw)\s*[-~]?%\}/A';
    private const RESERVED = ['true', 'false', 'null', 'none', '_self', '_context', '_charset'];

    /** $source with each wiki action call written in plain text replaced by its Twig equivalent, on the same lines. */
    public static function rewrite(string $source): string
    {
        $output = '';
        $last = 0;
        foreach (self::calls($source) as $call) {
            $output .= substr($source, $last, $call['offset'] - $last) . self::compile($call);
            $last = $call['offset'] + $call['length'];
        }

        return $last === 0 ? $source : $output . substr($source, $last);
    }

    /**
     * The wiki action calls written in the plain text of $source, in order.
     *
     * @return list<array{offset: int, length: int, line: int, name: string, params: array<string, string>, bare: bool, newlines: int}>
     */
    public static function calls(string $source): array
    {
        $calls = [];
        $length = strlen($source);
        $position = 0;
        while ($position < $length && preg_match('/\{[{%#]/', $source, $opener, PREG_OFFSET_CAPTURE, $position)) {
            $start = $opener[0][1];
            if ($opener[0][0] === '{#') {
                $end = strpos($source, '#}', $start + 2);
                $position = $end === false ? $length : $end + 2;
            } elseif ($opener[0][0] === '{%') {
                $position = preg_match(self::RAW_BLOCK, $source, $raw, 0, $start)
                    ? self::skipRawBlock($source, $start + strlen($raw[0]), $raw[1])
                    : self::skipExpression($source, $start + 2, '%}');
            } elseif (($call = self::callAt($source, $start)) !== null) {
                $calls[] = $call;
                $position = $start + $call['length'];
            } else {
                $position = self::skipExpression($source, $start + 2, '}}');
            }
        }

        return $calls;
    }

    /** @return array{offset: int, length: int, line: int, name: string, params: array<string, string>, bare: bool, newlines: int}|null */
    private static function callAt(string $source, int $start): ?array
    {
        if (!preg_match(self::CALL, $source, $match, 0, $start)) {
            return null;
        }
        [$text, $name, $attributes, $slash] = $match;
        $bare = $attributes === '' && $slash === '';
        if ($bare && ($text !== '{{' . $name . '}}' || !preg_match('/^[a-zA-Z_]\w*$/', $name) || in_array(strtolower($name), self::RESERVED, true))) {
            return null;
        }
        preg_match_all('/([a-zA-Z0-9_-]+)="([^"]*)"/', $attributes, $pairs, PREG_SET_ORDER);
        $params = [];
        foreach ($pairs as [, $key, $value]) {
            $params[$key] = $value;
        }

        return [
            'offset' => $start,
            'length' => strlen($text),
            'line' => substr_count($source, "\n", 0, $start) + 1,
            'name' => $name,
            'params' => $params,
            'bare' => $bare,
            'newlines' => substr_count($text, "\n"),
        ];
    }

    /** @param array{name: string, params: array<string, string>, bare: bool, newlines: int} $call */
    private static function compile(array $call): string
    {
        $name = $call['name'];
        if ($call['bare']) {
            return "{% if $name is defined %}{{ $name }}{% else %}{{ optionalAction(" . self::literal($name) . ')|raw }}{% endif %}';
        }
        $padding = str_repeat("\n", $call['newlines']);
        if (strtolower($name) === 'end' && isset($call['params']['elem'])) {
            return '{{ closeAction(' . self::literal($call['params']['elem']) . ")|raw$padding }}";
        }
        $params = [];
        foreach ($call['params'] as $key => $value) {
            $params[] = self::literal($key) . ': ' . self::literal($value);
        }
        $arguments = $params === [] ? '' : ', {' . implode(', ', $params) . '}';

        return '{{ renderAction(' . self::literal($name) . "$arguments)|raw$padding }}";
    }

    private static function literal(string $value): string
    {
        return "'" . strtr($value, ['\\' => '\\\\', "'" => "\\'"]) . "'";
    }

    /** The position just past the `{% end… %}` closing a verbatim or raw block. */
    private static function skipRawBlock(string $source, int $position, string $tag): int
    {
        if (preg_match('/\{%[-~]?\s*end' . $tag . '\s*[-~]?%\}/', $source, $end, PREG_OFFSET_CAPTURE, $position)) {
            return $end[0][1] + strlen($end[0][0]);
        }

        return strlen($source);
    }

    /** The position just past $end, skipping string literals and anything inside brackets the way Twig's lexer does. */
    private static function skipExpression(string $source, int $position, string $end): int
    {
        $length = strlen($source);
        $brackets = 0;
        while ($position < $length) {
            if ($brackets === 0 && substr_compare($source, $end, $position, strlen($end)) === 0) {
                return $position + strlen($end);
            }
            $char = $source[$position];
            if ($char === '"' || $char === "'") {
                $position = self::skipString($source, $position);
                continue;
            }
            if ($char === '(' || $char === '[' || $char === '{') {
                $brackets++;
            } elseif (($char === ')' || $char === ']' || $char === '}') && $brackets > 0) {
                $brackets--;
            }
            $position++;
        }

        return $length;
    }

    /** The position just past the string literal opening at $position, interpolations included. */
    private static function skipString(string $source, int $position): int
    {
        $length = strlen($source);
        $quote = $source[$position];
        $position++;
        while ($position < $length) {
            $char = $source[$position];
            if ($char === '\\') {
                $position += 2;
            } elseif ($char === $quote) {
                return $position + 1;
            } elseif ($quote === '"' && $char === '#' && ($source[$position + 1] ?? '') === '{') {
                $position = self::skipExpression($source, $position + 2, '}');
            } else {
                $position++;
            }
        }

        return $length;
    }
}
