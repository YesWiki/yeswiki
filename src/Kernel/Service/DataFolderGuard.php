<?php

namespace YesWiki\Kernel\Service;

/** Keeps Apache from running PHP out of the data folders, through a block of rules in each one's .htaccess (replicas 10). */
final class DataFolderGuard
{
    public const FOLDERS = ['cache', 'custom', 'files'];

    public const BEGIN = '# BEGIN YesWiki: no PHP runs from this folder';

    public const END = '# END YesWiki';

    private const RULES = <<<'HTACCESS'
        <FilesMatch "\.(php[0-9]?|phtml|pht|phar|phps)$">
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
            <IfModule !mod_authz_core.c>
                Order allow,deny
                Deny from all
            </IfModule>
        </FilesMatch>
        HTACCESS;

    /** The block as it is written, between its markers. */
    public static function block(): string
    {
        return self::BEGIN . "\n" . self::RULES . "\n" . self::END . "\n";
    }

    /** Writes the block into each data folder that has no .htaccess at all: two stats per folder, cheap enough for every request. */
    public static function provision(string $instanceDir): void
    {
        foreach (self::FOLDERS as $folder) {
            $directory = $instanceDir . '/' . $folder;
            if (is_dir($directory) && !is_file($directory . '/.htaccess')) {
                @file_put_contents($directory . '/.htaccess', self::block());
            }
        }
    }

    /**
     * Adds the block to each data folder's .htaccess that lacks it, below what the file already says.
     *
     * @return list<string> the folders whose .htaccess was written
     */
    public static function guard(string $instanceDir): array
    {
        $written = [];
        foreach (self::FOLDERS as $folder) {
            $directory = $instanceDir . '/' . $folder;
            if (!is_dir($directory)) {
                continue;
            }
            $file = $directory . '/.htaccess';
            $existing = is_file($file) ? (string)file_get_contents($file) : null;
            if ($existing !== null && str_contains($existing, self::BEGIN)) {
                continue;
            }
            $content = $existing === null || trim($existing) === '' ? self::block() : rtrim($existing) . "\n\n" . self::block();
            if (file_put_contents($file, $content) !== false) {
                $written[] = $folder;
            }
        }

        return $written;
    }
}
