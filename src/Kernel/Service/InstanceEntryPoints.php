<?php

/* The files that make a folder a farm Instance of a Program tree: index.php, yeswicli and the data folders. */

namespace YesWiki\Kernel\Service;

final class InstanceEntryPoints
{
    public const DATA_FOLDERS = ['cache', 'custom', 'files', 'private'];

    /** Write index.php, yeswicli and the data folders into $path, or say what could not be written. */
    public static function write(string $path, string $programDir): ?string
    {
        $exportedProgramDir = var_export($programDir, true);
        $index = <<<PHP
            <?php

            define('YESWIKI_PROGRAM_DIR', $exportedProgramDir);
            putenv('YESWIKI_CONFIG_FILE=' . __DIR__ . '/yeswiki.config.php');
            require YESWIKI_PROGRAM_DIR . '/index.php';

            PHP;
        if (file_put_contents($path . '/index.php', $index) === false) {
            return "Could not write $path/index.php";
        }

        foreach (self::DATA_FOLDERS as $dataFolder) {
            if (!is_dir($path . '/' . $dataFolder)) {
                @mkdir($path . '/' . $dataFolder, 0755, true);
            }
        }
        if (!is_file($path . '/private/.htaccess') && is_file($programDir . '/private/.htaccess')) {
            @copy($programDir . '/private/.htaccess', $path . '/private/.htaccess');
        }

        $console = $programDir . '/src/commands/console';
        $wrapper = <<<SH
            #!/usr/bin/env bash
            cd "\$(dirname "\${BASH_SOURCE[0]}")" || exit 1
            export YESWIKI_INSTANCE_DIR="\$PWD"
            export YESWIKI_CONFIG_FILE="\$PWD/yeswiki.config.php"
            php {$console} "\$@"

            SH;
        if (file_put_contents($path . '/yeswicli', $wrapper) !== false) {
            @chmod($path . '/yeswicli', 0755);
        }

        return null;
    }

    /** Whether $path/index.php is already an Instance entry point rather than a Program's own. */
    public static function isInstance(string $path): bool
    {
        $index = @file_get_contents($path . '/index.php');

        return is_string($index) && str_contains($index, "define('YESWIKI_PROGRAM_DIR'");
    }
}
