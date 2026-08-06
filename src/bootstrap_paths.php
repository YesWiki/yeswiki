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

/*
 * A few assets are written as bare source paths, in markup and in scripts, and so are asked
 * for at the instance's own docroot rather than at a published URL. The icon sprite is the
 * one that matters: some 250 `<use href="src/assets/icons.svg#name">` across templates, PHP
 * and JS -- every icon in the interface. Rewriting all of them to a published URL would not
 * even cover the scripts, which build that markup in the browser.
 *
 * On a farm instance those paths point at a docroot that holds no sources, so they answered
 * only where the vhost sends missing files through index.php. Give the instance the file
 * instead: 40K, refreshed when the source changes, and every one of those references works
 * whatever the webserver does. Standalone installs already have it and are left alone.
 */
if (YESWIKI_INSTANCE_DIR !== YESWIKI_SOURCE_DIR) {
    foreach (['src/assets/icons.svg'] as $yeswikiBareAsset) {
        $yeswikiBareSource = YESWIKI_SOURCE_DIR . '/' . $yeswikiBareAsset;
        $yeswikiBareTarget = YESWIKI_INSTANCE_DIR . '/' . $yeswikiBareAsset;
        // `!==`, not `>=`: the copy is stamped with the source's own mtime just below, so
        // equal means "this is the copy of exactly this source" and anything else means
        // recopy. `>=` skipped whenever the source was OLDER -- a rollback to a previous
        // release, an rsync or tar that preserves times, a restore from backup -- and left
        // the instance serving a sprite from a version it is no longer running, with no way
        // to notice: a missing icon renders as nothing at all.
        if (!is_file($yeswikiBareSource)
            || (is_file($yeswikiBareTarget) && filemtime($yeswikiBareTarget) === filemtime($yeswikiBareSource))) {
            continue;
        }
        if (!is_dir(\dirname($yeswikiBareTarget))) {
            @mkdir(\dirname($yeswikiBareTarget), 0755, true);
        }
        // through a temp name, so a request never reads a half-written sprite
        $yeswikiBareTmp = $yeswikiBareTarget . '.' . getmypid() . '.tmp';
        if (@copy($yeswikiBareSource, $yeswikiBareTmp)) {
            @touch($yeswikiBareTmp, filemtime($yeswikiBareSource) ?: time());
            if (!@rename($yeswikiBareTmp, $yeswikiBareTarget)) {
                @unlink($yeswikiBareTmp);
            }
        }
    }
    unset($yeswikiBareAsset, $yeswikiBareSource, $yeswikiBareTarget, $yeswikiBareTmp);
}
