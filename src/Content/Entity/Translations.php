<?php

namespace YesWiki\Content\Entity;

/** The translations a Content body carries, and the paths they address. */
final class Translations
{
    /** The body key holding every language's translations. */
    public const BODY_KEY = '__translations';

    /** The keys a list element is identified by, so a path names an element rather than its position. */
    private const IDENTITY_KEYS = ['name', 'id'];

    /**
     * The languages $body carries at least one translation for.
     *
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    public static function languages(array $body): array
    {
        $store = $body[self::BODY_KEY] ?? null;
        if (!is_array($store)) {
            return [];
        }

        $languages = [];
        foreach ($store as $language => $values) {
            if (is_string($language) && is_array($values) && $values !== []) {
                $languages[] = $language;
            }
        }
        sort($languages);

        return $languages;
    }

    /**
     * What $body says in $language, as path => text.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, string>
     */
    public static function of(array $body, string $language): array
    {
        $values = $body[self::BODY_KEY][$language] ?? null;
        if (!is_array($values)) {
            return [];
        }

        $texts = [];
        foreach ($values as $path => $text) {
            if (is_string($path) && is_scalar($text) && (string)$text !== '') {
                $texts[$path] = (string)$text;
            }
        }

        return $texts;
    }

    /**
     * $body as a reader of $language sees it, with the translation store removed.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public static function apply(array $body, string $language): array
    {
        if (!isset($body[self::BODY_KEY])) {
            return $body;
        }

        foreach (self::of($body, $language) as $path => $text) {
            self::put($body, self::segments($path), $text);
        }

        return self::strip($body);
    }

    /**
     * $body with each path => text written into it, creating a leaf but never a container.
     *
     * @param array<string, mixed>  $body
     * @param array<string, string> $values
     *
     * @return array<string, mixed>
     */
    public static function applied(array $body, array $values): array
    {
        foreach ($values as $path => $text) {
            self::put($body, self::segments((string)$path), $text);
        }

        return $body;
    }

    /**
     * $body with the translation store removed and nothing else changed.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public static function strip(array $body): array
    {
        unset($body[self::BODY_KEY]);

        return $body;
    }

    /**
     * $body with $language's translations replaced by $values; blank texts and empty languages drop out.
     *
     * @param array<string, mixed>  $body
     * @param array<string, string> $values
     *
     * @return array<string, mixed>
     */
    public static function with(array $body, string $language, array $values): array
    {
        $store = is_array($body[self::BODY_KEY] ?? null) ? $body[self::BODY_KEY] : [];

        $kept = [];
        foreach ($values as $path => $text) {
            $text = trim((string)$text);
            if ($path !== '' && $text !== '') {
                $kept[$path] = $text;
            }
        }

        if ($kept === []) {
            unset($store[$language]);
        } else {
            $store[$language] = $kept;
        }

        ksort($store);

        if ($store === []) {
            return self::strip($body);
        }

        $body[self::BODY_KEY] = $store;

        return $body;
    }

    /**
     * $target carrying $source's translations, unless $target already brings its own.
     *
     * @param array<string, mixed> $source
     * @param array<string, mixed> $target
     *
     * @return array<string, mixed>
     */
    public static function carriedOver(array $source, array $target): array
    {
        if (isset($target[self::BODY_KEY]) || !isset($source[self::BODY_KEY])) {
            return $target;
        }

        $target[self::BODY_KEY] = $source[self::BODY_KEY];

        return $target;
    }

    /**
     * $body with every translation addressing $from re-addressed to $to, in every language.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public static function renamedPath(array $body, string $from, string $to): array
    {
        $store = $body[self::BODY_KEY] ?? null;
        if (!is_array($store) || $from === '' || $to === '' || $from === $to) {
            return $body;
        }

        foreach ($store as $language => $values) {
            if (!is_array($values)) {
                continue;
            }
            $renamed = [];
            foreach ($values as $path => $text) {
                $renamed[self::rename((string)$path, $from, $to)] = $text;
            }
            $store[$language] = $renamed;
        }

        $body[self::BODY_KEY] = $store;

        return $body;
    }

    /**
     * $body with every translation addressing $path dropped, in every language.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public static function withoutPath(array $body, string $path): array
    {
        $store = $body[self::BODY_KEY] ?? null;
        if (!is_array($store) || $path === '') {
            return $body;
        }

        foreach ($store as $language => $values) {
            if (!is_array($values)) {
                continue;
            }
            foreach (array_keys($values) as $translated) {
                if (self::addresses((string)$translated, $path)) {
                    unset($values[$translated]);
                }
            }
            if ($values === []) {
                unset($store[$language]);
            } else {
                $store[$language] = $values;
            }
        }

        if ($store === []) {
            return self::strip($body);
        }

        $body[self::BODY_KEY] = $store;

        return $body;
    }

    /**
     * $body without the translations whose path no longer names anything in it -- a deleted field, a removed list node.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public static function pruned(array $body): array
    {
        $store = $body[self::BODY_KEY] ?? null;
        if (!is_array($store)) {
            return $body;
        }

        foreach ($store as $language => $values) {
            if (!is_array($values)) {
                unset($store[$language]);

                continue;
            }
            foreach (array_keys($values) as $path) {
                if (self::sourceTextAt($body, (string)$path) === '') {
                    unset($values[$path]);
                }
            }
            if ($values === []) {
                unset($store[$language]);
            } else {
                $store[$language] = $values;
            }
        }

        if ($store === []) {
            return self::strip($body);
        }

        $body[self::BODY_KEY] = $store;

        return $body;
    }

    /**
     * The text $body holds at $path, in the language the body itself is written in.
     *
     * @param array<string, mixed> $body
     */
    public static function sourceTextAt(array $body, string $path): string
    {
        $node = $body;
        foreach (self::segments($path) as $segment) {
            if (!is_array($node)) {
                return '';
            }
            $key = self::indexOf($node, $segment);
            if ($key === null) {
                return '';
            }
            $node = $node[$key];
        }

        return is_scalar($node) ? (string)$node : '';
    }

    /**
     * @return list<string>
     */
    private static function segments(string $path): array
    {
        return array_values(array_filter(explode('.', $path), fn ($segment) => $segment !== ''));
    }

    /** Whether $path names $prefix itself or something inside it. */
    private static function addresses(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '.');
    }

    private static function rename(string $path, string $from, string $to): string
    {
        if (!self::addresses($path, $from)) {
            return $path;
        }

        return $to . substr($path, strlen($from));
    }

    /**
     * Write $value at $segments, creating the leaf but never a container that is not already there.
     *
     * @param array<array-key, mixed> $node
     * @param list<string>            $segments
     */
    private static function put(array &$node, array $segments, string $value): void
    {
        $segment = array_shift($segments);
        if ($segment === null) {
            return;
        }

        $key = self::indexOf($node, $segment);

        if ($segments === []) {
            if ($key === null) {
                if ($node !== [] && array_is_list($node)) {
                    return;
                }
                $node[$segment] = $value;

                return;
            }
            if (is_array($node[$key])) {
                return;
            }
            $node[$key] = $value;

            return;
        }

        if ($key === null || !is_array($node[$key])) {
            return;
        }

        self::put($node[$key], $segments, $value);
    }

    /**
     * Where $segment lives in $node: its own key, or the list element that identifies itself by that name.
     *
     * @param array<array-key, mixed> $node
     */
    private static function indexOf(array $node, string $segment): int|string|null
    {
        if (array_key_exists($segment, $node)) {
            return $segment;
        }

        foreach ($node as $index => $child) {
            if (!is_array($child)) {
                continue;
            }
            foreach (self::IDENTITY_KEYS as $identityKey) {
                if (isset($child[$identityKey]) && is_scalar($child[$identityKey]) && (string)$child[$identityKey] === $segment) {
                    return $index;
                }
            }
        }

        return null;
    }
}
