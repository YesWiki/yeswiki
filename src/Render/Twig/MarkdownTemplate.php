<?php

namespace YesWiki\Render\Twig;

/** A Twig template written in markdown: converted once at compile time, so what a template prints is never read as markdown. */
final class MarkdownTemplate
{
    public const MARKER = '{# markdown #}';

    public static function isMarked(string $source): bool
    {
        return str_starts_with($source, self::MARKER);
    }

    /** $body with the marker that makes it a markdown template. */
    public static function mark(string $body): string
    {
        return self::MARKER . "\n" . $body;
    }

    /** $source without its marker. */
    public static function unmark(string $source): string
    {
        return self::isMarked($source) ? ltrim(substr($source, strlen(self::MARKER)), "\r\n") : $source;
    }

    /**
     * The Twig source $source means, its markdown converted by $toHtml around every Twig construct.
     *
     * @param callable(string): string $toHtml
     */
    public static function compile(string $source, callable $toHtml): string
    {
        $prefix = 'ywtwig' . substr(md5($source), 0, 8) . 'n';
        $tokens = [];
        $masked = '';
        $last = 0;
        foreach (WikiActionSyntax::constructs($source) as [$offset, $length]) {
            $token = $prefix . count($tokens) . 'z';
            $tokens[$token] = substr($source, $offset, $length);
            $masked .= substr($source, $last, $offset - $last) . $token;
            $last = $offset + $length;
        }
        $html = $toHtml($masked . substr($source, $last));

        $placeholder = $prefix . '\d+z';
        $html = (string)preg_replace('~\sid="[^"]*' . $placeholder . '[^"]*"~', '', $html);
        $html = (string)preg_replace_callback(
            '~<p>((?:' . $placeholder . '\s*)+)</p>\n?~',
            function (array $match) use ($tokens): string {
                preg_match_all('~\S+~', $match[1], $found);
                foreach ($found[0] as $token) {
                    if (self::printsBlocks($tokens[$token] ?? '')) {
                        return $match[1];
                    }
                }

                return $match[0];
            },
            $html
        );

        return strtr($html, $tokens);
    }

    /** Whether a construct alone in a paragraph must not keep that paragraph: it prints no text of its own, or prints HTML that may hold blocks. */
    private static function printsBlocks(string $token): bool
    {
        return !str_starts_with($token, '{{') || preg_match('/\|\s*raw\b|Action\(/', $token) === 1;
    }
}
