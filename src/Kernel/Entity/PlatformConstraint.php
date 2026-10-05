<?php

namespace YesWiki\Kernel\Entity;

/** Whether this PHP satisfies a manifest's `require` entry, for the subset of Composer constraints an extension states. */
final class PlatformConstraint
{
    /** Whether $version satisfies $constraint: `*`, `^8.3`, `~8.3`, `>=8.3 <9`, `8.3.*`, `8.3`, joined by `||`. */
    public static function allows(string $constraint, string $version): bool
    {
        foreach (explode('||', $constraint) as $alternative) {
            $atoms = preg_split('/[\s,]+/', trim($alternative), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($atoms !== [] && array_reduce($atoms, fn (bool $all, string $atom): bool => $all && self::atom($atom, $version), true)) {
                return true;
            }
        }

        return false;
    }

    /** The running PHP's version without its suffix, the way a constraint names it. */
    public static function phpVersion(): string
    {
        return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
    }

    private static function atom(string $atom, string $version): bool
    {
        if ($atom === '*') {
            return true;
        }
        if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $atom, $m) === 1) {
            $floor = self::padded($m);

            return version_compare($version, $floor, '>=') && version_compare($version, ((int)$m[1] + 1) . '.0.0', '<');
        }
        if (preg_match('/^~(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $atom, $m) === 1) {
            $floor = self::padded($m);
            $ceiling = isset($m[3]) ? $m[1] . '.' . ((int)$m[2] + 1) . '.0' : ((int)$m[1] + 1) . '.0.0';

            return version_compare($version, $floor, '>=') && version_compare($version, $ceiling, '<');
        }
        if (preg_match('/^(\d+(?:\.\d+)*)\.\*$/', $atom, $m) === 1) {
            return str_starts_with($version . '.', $m[1] . '.');
        }
        if (preg_match('/^(>=|<=|>|<|!=|==|=)?v?(\d+(?:\.\d+){0,2})$/', $atom, $m) === 1) {
            $operator = $m[1] === '' || $m[1] === '=' ? '==' : $m[1];
            $target = $m[2];
            if ($operator === '==' && substr_count($target, '.') < 2) {
                return str_starts_with($version . '.', $target . '.');
            }

            return version_compare($version, $target, $operator);
        }

        return false;
    }

    /** @param array<int, string> $m */
    private static function padded(array $m): string
    {
        return $m[1] . '.' . (($m[2] ?? '') === '' ? '0' : $m[2]) . '.' . (($m[3] ?? '') === '' ? '0' : $m[3]);
    }
}
