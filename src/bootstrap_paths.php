<?php

if (!defined('YESWIKI_SOURCE_DIR')) {
    define('YESWIKI_SOURCE_DIR', \dirname(__DIR__));
}
if (!defined('YESWIKI_INSTANCE_DIR')) {
    define('YESWIKI_INSTANCE_DIR', (string)getcwd());
}

foreach (['cache', 'custom', 'files', 'private'] as $yeswikiDataFolder) {
    $yeswikiDataDir = YESWIKI_INSTANCE_DIR . '/' . $yeswikiDataFolder;
    if (!is_dir($yeswikiDataDir)) {
        @mkdir($yeswikiDataDir, 0755, true);
    }
}
unset($yeswikiDataFolder, $yeswikiDataDir);

if (!is_file(YESWIKI_INSTANCE_DIR . '/private/.htaccess') && is_file(YESWIKI_SOURCE_DIR . '/private/.htaccess')) {
    @copy(YESWIKI_SOURCE_DIR . '/private/.htaccess', YESWIKI_INSTANCE_DIR . '/private/.htaccess');
}

if (YESWIKI_INSTANCE_DIR !== YESWIKI_SOURCE_DIR) {
    foreach (['src/assets/icons.svg'] as $yeswikiBareAsset) {
        $yeswikiBareSource = YESWIKI_SOURCE_DIR . '/' . $yeswikiBareAsset;
        $yeswikiBareTarget = YESWIKI_INSTANCE_DIR . '/' . $yeswikiBareAsset;

        if (!is_file($yeswikiBareSource)
            || (is_file($yeswikiBareTarget) && filemtime($yeswikiBareTarget) === filemtime($yeswikiBareSource))) {
            continue;
        }
        if (!is_dir(\dirname($yeswikiBareTarget))) {
            @mkdir(\dirname($yeswikiBareTarget), 0755, true);
        }

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
