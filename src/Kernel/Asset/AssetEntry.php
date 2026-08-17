<?php

namespace YesWiki\Kernel\Asset;

/**
 * One declared asset: a stylesheet, a script, or an inline block of either.
 *
 * @see AssetRegistry
 * @see docs/adr/0014-assets-are-declared-by-a-render-not-accumulated-by-a-request.md
 */
final class AssetEntry
{
    public const CSS_FILE = 'css-file';
    public const CSS_INLINE = 'css-inline';
    public const JS_FILE = 'js-file';
    public const JS_INLINE = 'js-inline';

    private function __construct(
        public readonly string $kind,
        /** Identity for deduplication: the resolved URL for files, a content hash for inline blocks. */
        public readonly string $key,
        /** The resolved URL, or the inline source. */
        public readonly string $payload,
        public readonly bool $module = false,
        /** Emitted before every non-first entry, and without `defer`. */
        public readonly bool $first = false,
        public readonly string $attributes = '',
        public readonly string $conditionStart = '',
        public readonly string $conditionEnd = '',
    ) {
    }

    public static function cssFile(
        string $url,
        string $conditionStart = '',
        string $conditionEnd = '',
        string $attributes = ''
    ): self {
        return new self(
            kind: self::CSS_FILE,
            key: $url,
            payload: $url,
            attributes: trim($attributes),
            conditionStart: $conditionStart,
            conditionEnd: $conditionEnd,
        );
    }

    public static function cssInline(string $style): self
    {
        return new self(
            kind: self::CSS_INLINE,
            key: 'inline:' . md5($style),
            payload: $style,
        );
    }

    public static function jsFile(string $url, bool $first = false, bool $module = false): self
    {
        return new self(
            kind: self::JS_FILE,
            key: $url,
            payload: $url,
            module: $module,
            first: $first,
        );
    }

    public static function jsInline(string $script, bool $module = false, bool $first = false): self
    {
        return new self(
            kind: self::JS_INLINE,
            key: 'inline:' . md5($script),
            payload: $script,
            module: $module,
            first: $first,
        );
    }

    public function isCss(): bool
    {
        return $this->kind === self::CSS_FILE || $this->kind === self::CSS_INLINE;
    }

    /** The URL this entry loads, or null for an inline block -- what the browser deduplicates on. */
    public function url(): ?string
    {
        return $this->kind === self::CSS_FILE || $this->kind === self::JS_FILE ? $this->payload : null;
    }

    public function toHtml(): string
    {
        switch ($this->kind) {
            case self::CSS_FILE:
                $attrs = $this->attributes === '' ? '' : ' ' . $this->attributes;
                $link = '<link rel="stylesheet" href="' . htmlspecialchars($this->payload, ENT_QUOTES) . '"' . $attrs . '>';

                return $this->conditionStart . $link . $this->conditionEnd . "\n";

            case self::CSS_INLINE:
                return '<style>' . "\n" . $this->payload . '</style>' . "\n";

            case self::JS_FILE:
                $attrs = $this->first ? '' : ' defer';
                $attrs .= $this->module ? ' type="module"' : '';

                return '<script src="' . htmlspecialchars($this->payload, ENT_QUOTES) . '"' . $attrs . '></script>' . "\n";

            case self::JS_INLINE:
            default:
                $type = $this->module ? ' type="module"' : '';

                return '<script' . $type . '>' . "\n" . $this->payload . '</script>' . "\n";
        }
    }
}
