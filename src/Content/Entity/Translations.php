<?php

namespace YesWiki\Content\Entity;

/** The translations a Content body carries, and the paths they address. */
final class Translations
{
    /** The body key holding every language's translations, nested the way Doryphore's `extralang` is. */
    public const BODY_KEY = '__extralang';

    /** Where translations lived before, flat as path => text; still read, never written. */
    public const LEGACY_KEY = '__translations';

    /** A form's own keys under the names Doryphore stores a form with. */
    private const FORM_KEYS = ['label' => 'title', 'template' => 'fields'];

    /** A form field's attributes under the names Doryphore gives them. */
    private const FIELD_ATTRIBUTES = ['hint' => 'helper'];

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
        $languages = array_map('strval', array_keys(self::store($body)));
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
        return self::store($body)[$language] ?? [];
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
        if (!self::carries($body)) {
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
        unset($body[self::BODY_KEY], $body[self::LEGACY_KEY]);

        return $body;
    }

    /**
     * $body with its translations rewritten under BODY_KEY, whichever key and shape they were in.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public static function inCurrentShape(array $body): array
    {
        return self::carries($body) ? self::withStore($body, self::store($body)) : $body;
    }

    /** @param array<string, mixed> $body */
    public static function carries(array $body): bool
    {
        return isset($body[self::BODY_KEY]) || isset($body[self::LEGACY_KEY]);
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
        $store = self::store($body);

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

        return self::withStore($body, $store);
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
        if (self::carries($target) || !self::carries($source)) {
            return $target;
        }

        return self::withStore($target, self::store($source));
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
        if (!self::carries($body) || $from === '' || $to === '' || $from === $to) {
            return $body;
        }

        $store = self::store($body);
        foreach ($store as $language => $values) {
            $renamed = [];
            foreach ($values as $path => $text) {
                $renamed[self::rename((string)$path, $from, $to)] = $text;
            }
            $store[$language] = $renamed;
        }

        return self::withStore($body, $store);
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
        if (!self::carries($body) || $path === '') {
            return $body;
        }

        $store = self::store($body);
        foreach ($store as $language => $values) {
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

        return self::withStore($body, $store);
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
        if (!self::carries($body)) {
            return $body;
        }

        $store = self::store($body);
        foreach ($store as $language => $values) {
            foreach (array_keys($values) as $path) {
                if (self::sourceTextAt($body, (string)$path) === '') {
                    unset($values[$path]);
                }
            }
            $store[$language] = $values;
        }

        return self::withStore($body, $store);
    }

    /**
     * $body with the translation store turned into objects all the way down, so `nodes.0` is written `{"0": ...}` as Doryphore writes it.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public static function forEncoding(array $body): array
    {
        if (is_array($body[self::BODY_KEY] ?? null)) {
            $body[self::BODY_KEY] = self::asObject($body[self::BODY_KEY]);
        }

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

    /**
     * Every language's translations as path => text, read from whichever key $body holds them under.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, array<string, string>>
     */
    private static function store(array $body): array
    {
        $store = [];
        if (is_array($body[self::BODY_KEY] ?? null)) {
            foreach ($body[self::BODY_KEY] as $language => $tree) {
                if (!is_array($tree)) {
                    continue;
                }
                $values = [];
                self::flatten($body, $tree, [], $values);
                if ($values !== []) {
                    $store[(string)$language] = $values;
                }
            }

            return $store;
        }

        foreach (is_array($body[self::LEGACY_KEY] ?? null) ? $body[self::LEGACY_KEY] : [] as $language => $values) {
            $texts = [];
            foreach (is_array($values) ? $values : [] as $path => $text) {
                if (is_string($path) && is_scalar($text) && (string)$text !== '') {
                    $texts[$path] = (string)$text;
                }
            }
            if ($texts !== []) {
                $store[(string)$language] = $texts;
            }
        }

        return $store;
    }

    /**
     * $body holding $store under BODY_KEY in Doryphore's shape, or no store at all when it is empty.
     *
     * @param array<string, mixed>                 $body
     * @param array<string, array<string, string>> $store
     *
     * @return array<string, mixed>
     */
    private static function withStore(array $body, array $store): array
    {
        $nested = [];
        foreach ($store as $language => $values) {
            $tree = [];
            foreach ($values as $path => $text) {
                $storagePath = self::toStorage($body, self::segments((string)$path));
                if ($storagePath !== null && (string)$text !== '') {
                    self::nest($tree, $storagePath, (string)$text);
                }
            }
            if ($tree !== []) {
                $nested[(string)$language] = $tree;
            }
        }
        ksort($nested);

        $body = self::strip($body);
        if ($nested !== []) {
            $body[self::BODY_KEY] = $nested;
        }

        return $body;
    }

    /**
     * @param array<string, mixed>    $body
     * @param array<array-key, mixed> $tree
     * @param list<string>            $storagePath
     * @param array<string, string>   $values
     */
    private static function flatten(array $body, array $tree, array $storagePath, array &$values): void
    {
        foreach ($tree as $key => $child) {
            $path = [...$storagePath, (string)$key];
            if (is_array($child)) {
                self::flatten($body, $child, $path, $values);
            } elseif (is_scalar($child) && (string)$child !== '') {
                $internal = self::fromStorage($body, $path);
                if ($internal !== null) {
                    $values[implode('.', $internal)] = (string)$child;
                }
            }
        }
    }

    /**
     * A path's segments as Doryphore stores them: a form under its own names, a list element by its position.
     *
     * @param array<string, mixed> $body
     * @param list<string>         $segments
     *
     * @return list<string>|null null when the path names an element $body no longer has
     */
    private static function toStorage(array $body, array $segments): ?array
    {
        if ($segments === []) {
            return null;
        }
        if (self::isForm($body)) {
            if ($segments[0] === 'template' && count($segments) === 3) {
                return [self::FORM_KEYS['template'], $segments[1], self::FIELD_ATTRIBUTES[$segments[2]] ?? $segments[2]];
            }
            $segments[0] = self::FORM_KEYS[$segments[0]] ?? $segments[0];

            return $segments;
        }

        $node = $body;
        $stored = [];
        foreach ($segments as $segment) {
            if (is_array($node) && $node !== [] && array_is_list($node)) {
                $index = self::indexOf($node, $segment);
                if ($index === null) {
                    return null;
                }
                $stored[] = (string)$index;
                $node = $node[$index] ?? null;

                continue;
            }
            $stored[] = $segment;
            $node = is_array($node) ? ($node[$segment] ?? null) : null;
        }

        return $stored;
    }

    /**
     * The inverse of toStorage(): a list element's position back to the name or id it is addressed by.
     *
     * @param array<string, mixed> $body
     * @param list<string>         $stored
     *
     * @return list<string>|null
     */
    private static function fromStorage(array $body, array $stored): ?array
    {
        if (self::isForm($body)) {
            $fields = array_flip(self::FORM_KEYS);
            if ($stored[0] === self::FORM_KEYS['template'] && count($stored) === 3) {
                return ['template', $stored[1], array_flip(self::FIELD_ATTRIBUTES)[$stored[2]] ?? $stored[2]];
            }
            $stored[0] = $fields[$stored[0]] ?? $stored[0];

            return $stored;
        }

        $node = $body;
        $segments = [];
        foreach ($stored as $segment) {
            if (is_array($node) && $node !== [] && array_is_list($node)) {
                $element = ctype_digit($segment) ? ($node[(int)$segment] ?? null) : null;
                $identity = is_array($element) ? self::identityOf($element) : null;
                if ($identity === null) {
                    return null;
                }
                $segments[] = $identity;
                $node = $element;

                continue;
            }
            $segments[] = $segment;
            $node = is_array($node) ? ($node[$segment] ?? null) : null;
        }

        return $segments;
    }

    /** @param array<array-key, mixed> $element */
    private static function identityOf(array $element): ?string
    {
        foreach (self::IDENTITY_KEYS as $identityKey) {
            if (isset($element[$identityKey]) && is_scalar($element[$identityKey]) && (string)$element[$identityKey] !== '') {
                return (string)$element[$identityKey];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $body */
    private static function isForm(array $body): bool
    {
        return is_array($body['template'] ?? null) && array_is_list($body['template']);
    }

    /**
     * @param array<array-key, mixed> $tree
     * @param list<string>            $path
     */
    private static function nest(array &$tree, array $path, string $text): void
    {
        $key = array_shift($path);
        if ($key === null) {
            return;
        }
        if ($path === []) {
            $tree[$key] = $text;

            return;
        }
        if (!is_array($tree[$key] ?? null)) {
            $tree[$key] = [];
        }
        self::nest($tree[$key], $path, $text);
    }

    /** @param array<array-key, mixed> $tree */
    private static function asObject(array $tree): \stdClass
    {
        $object = new \stdClass();
        foreach ($tree as $key => $child) {
            $object->{(string)$key} = is_array($child) ? self::asObject($child) : $child;
        }

        return $object;
    }
}
