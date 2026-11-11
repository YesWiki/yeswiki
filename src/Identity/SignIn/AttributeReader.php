<?php

namespace YesWiki\Identity\SignIn;

/**
 * Reads attributes sent by an identity provider, whatever their shape:
 * missing, scalar, list, nested or binary.
 */
final class AttributeReader
{
    /**
     * First usable value of an attribute, as a string.
     *
     * @param array<array-key, mixed> $attributes
     */
    public static function one(array $attributes, string $name): ?string
    {
        return self::many($attributes, $name)[0] ?? null;
    }

    /**
     * Every usable value of an attribute (a single value or a list).
     *
     * @param array<array-key, mixed> $attributes
     *
     * @return list<string>
     */
    public static function many(array $attributes, string $name): array
    {
        if (!array_key_exists($name, $attributes)) {
            return [];
        }
        $raw = $attributes[$name];
        $candidates = is_array($raw) ? $raw : [$raw];
        $values = [];
        foreach ($candidates as $candidate) {
            $value = self::usable($candidate);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * A copy of the attributes safe to write to a log: binary values are
     * described, and the whole stays under $maxBytes once encoded as JSON.
     * When too large, the largest keys are dropped and listed under `_truncated`.
     *
     * @param array<array-key, mixed> $attributes
     *
     * @return array<array-key, mixed>
     */
    public static function forLog(array $attributes, int $maxBytes = 16384): array
    {
        $logged = [];
        foreach ($attributes as $key => $value) {
            $logged[$key] = self::describe($value);
        }

        $sizes = [];
        foreach ($logged as $key => $value) {
            $sizes[$key] = self::size($value);
        }
        arsort($sizes);

        $dropped = [];
        while (self::size($dropped === [] ? $logged : $logged + ['_truncated' => $dropped]) > $maxBytes && $sizes !== []) {
            $key = array_key_first($sizes);
            unset($sizes[$key], $logged[$key]);
            $dropped[] = (string)$key;
        }
        if ($dropped !== []) {
            $logged['_truncated'] = $dropped;
        }

        return $logged;
    }

    private static function usable(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (!is_string($value) || $value === '' || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        return $value;
    }

    private static function describe(mixed $value): mixed
    {
        if (is_string($value)) {
            return mb_check_encoding($value, 'UTF-8') ? $value : '(binary, ' . strlen($value) . ' bytes)';
        }
        if (is_array($value)) {
            return array_map([self::class, 'describe'], $value);
        }
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return '(' . get_debug_type($value) . ')';
    }

    private static function size(mixed $value): int
    {
        return strlen((string)json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
}
