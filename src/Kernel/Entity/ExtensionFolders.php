<?php

namespace YesWiki\Kernel\Entity;

/** Every extension under a root: each visible folder is one, named by the folder (ADR-0029). */
final class ExtensionFolders
{
    private const NAME = '/^[A-Za-z0-9][A-Za-z0-9_-]*$/';

    /** @return array<string, string> folder name => path with a trailing slash, sorted by name */
    public static function in(string $root): array
    {
        $root = rtrim($root, '/');
        $entries = is_dir($root) ? scandir($root) : false;
        if ($entries === false) {
            return [];
        }

        $found = [];
        foreach ($entries as $entry) {
            if (preg_match(self::NAME, $entry) === 1 && is_dir($root . '/' . $entry)) {
                $found[$entry] = $root . '/' . $entry . '/';
            }
        }
        ksort($found);

        return $found;
    }

    /**
     * The extensions an Instance can see, its own shadowing the Program's shared ones of the same name.
     *
     * @return array<string, string> folder name => path with a trailing slash
     */
    public static function visible(string $programDir, string $instanceDir): array
    {
        return array_replace(self::in($programDir . '/extensions'), self::in($instanceDir . '/custom/extensions'));
    }

    /**
     * Which of $folders the configuration switched on and does not forbid, in the order they were found.
     *
     * @param array<string, string> $folders
     *
     * @return array<string, string>
     */
    public static function active(array $folders, mixed $activeExtensions, mixed $forbiddenExtensions = []): array
    {
        $names = array_diff(self::names($activeExtensions), self::forbidden($forbiddenExtensions));

        return array_filter($folders, fn (string $name): bool => in_array($name, $names, true), ARRAY_FILTER_USE_KEY);
    }

    /**
     * The extensions `forbidden_extensions` keeps off an Instance; core itself can never be one of them.
     *
     * @return list<string>
     */
    public static function forbidden(mixed $forbiddenExtensions): array
    {
        return array_values(array_diff(self::names($forbiddenExtensions), ['yeswiki']));
    }

    /** @return list<string> */
    private static function names(mixed $listed): array
    {
        return array_values(array_unique(array_map('strval', is_array($listed) ? array_values($listed) : [])));
    }

    /**
     * Which of $folders a Doryphore-era `desc.xml` switched on with `active="1"`, the only switch there was before `active_extensions`.
     *
     * @param array<string, string> $folders
     *
     * @return list<string>
     */
    public static function activeByLegacyDescriptor(array $folders): array
    {
        $active = [];
        foreach ($folders as $name => $path) {
            $descriptor = rtrim($path, '/') . '/desc.xml';
            $xml = is_file($descriptor) ? (string)file_get_contents($descriptor) : '';
            if (preg_match('/<plugin\b[^>]*\bactive="([^"]*)"/', $xml, $m) === 1 && in_array(strtolower($m[1]), ['1', 'true', 'yes'], true)) {
                $active[] = (string)$name;
            }
        }

        return $active;
    }
}
