<?php

/**
 * Source dir vs instance dir separation, for wiki farms sharing one YesWiki source tree.
 *
 * - YESWIKI_SOURCE_DIR: where the YesWiki code lives (src/, extensions/, themes/, lang/,
 *   templates/, vendor/...). A farm instance's index.php defines it before requiring
 *   the shared index.php; standalone installs fall back to this file's own tree.
 * - YESWIKI_INSTANCE_DIR: where this wiki's data lives (yeswiki.config.php, files/, custom/,
 *   cache/, private/). Always the current working directory - PHP sets cwd to the directory
 *   of the executed script, i.e. the instance's index.php location; the whole codebase
 *   already relies on that convention for data paths.
 *
 * In a standalone install both constants point to the same directory, so every
 * YESWIKI_SOURCE_DIR-prefixed path is identical to the historical cwd-relative one.
 *
 * Kept dependency-free: this must be loadable before the autoloader and the vendor dir.
 */
if (!defined('YESWIKI_SOURCE_DIR')) {
    define('YESWIKI_SOURCE_DIR', \dirname(__DIR__));
}
if (!defined('YESWIKI_INSTANCE_DIR')) {
    define('YESWIKI_INSTANCE_DIR', (string)getcwd());
}

// Auto-provision the instance data folders, so a folder containing only index.php
// (+ yeswiki.config.php) can become a wiki.
foreach (['cache', 'custom', 'files', 'private'] as $yeswikiDataFolder) {
    $yeswikiDataDir = YESWIKI_INSTANCE_DIR . '/' . $yeswikiDataFolder;
    if (!is_dir($yeswikiDataDir)) {
        @mkdir($yeswikiDataDir, 0755, true);
    }
}
unset($yeswikiDataFolder, $yeswikiDataDir);

// private/ must never be web-served: ship the deny-all .htaccess for Apache setups
// (nginx setups deny it in the vhost, see docker/nginx.conf)
if (!is_file(YESWIKI_INSTANCE_DIR . '/private/.htaccess') && is_file(YESWIKI_SOURCE_DIR . '/private/.htaccess')) {
    @copy(YESWIKI_SOURCE_DIR . '/private/.htaccess', YESWIKI_INSTANCE_DIR . '/private/.htaccess');
}
