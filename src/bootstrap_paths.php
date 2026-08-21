<?php

/** A directory stated by an already-defined constant, then the environment, then a fallback, resolved absolute. */
$yeswikiStatedDir = static function (string $constant, string $fallback): string {
    $stated = getenv($constant);
    $candidate = ($stated === false || trim($stated) === '') ? $fallback : $stated;

    if (trim($candidate) === '') {
        throw new RuntimeException("{$constant} is not set and could not be inferred; state it in the environment.");
    }

    $resolved = realpath($candidate);
    if ($resolved === false || !is_dir($resolved)) {
        throw new RuntimeException("{$constant} resolves to '{$candidate}', which is not a directory that exists.");
    }

    return $resolved;
};

if (!defined('YESWIKI_PROGRAM_DIR')) {
    define('YESWIKI_PROGRAM_DIR', $yeswikiStatedDir('YESWIKI_PROGRAM_DIR', \dirname(__DIR__)));
}
if (!defined('YESWIKI_INSTANCE_DIR')) {
    define('YESWIKI_INSTANCE_DIR', $yeswikiStatedDir('YESWIKI_INSTANCE_DIR', (string)getcwd()));
}
unset($yeswikiStatedDir);

foreach (['cache', 'custom', 'files', 'private'] as $yeswikiDataFolder) {
    $yeswikiDataDir = YESWIKI_INSTANCE_DIR . '/' . $yeswikiDataFolder;
    if (!is_dir($yeswikiDataDir)) {
        @mkdir($yeswikiDataDir, 0755, true);
    }
}
unset($yeswikiDataFolder, $yeswikiDataDir);

if (!is_file(YESWIKI_INSTANCE_DIR . '/private/.htaccess') && is_file(YESWIKI_PROGRAM_DIR . '/private/.htaccess')) {
    @copy(YESWIKI_PROGRAM_DIR . '/private/.htaccess', YESWIKI_INSTANCE_DIR . '/private/.htaccess');
}

if (YESWIKI_INSTANCE_DIR !== YESWIKI_PROGRAM_DIR) {
    foreach (['src/assets/icons.svg'] as $yeswikiBareAsset) {
        $yeswikiBareSource = YESWIKI_PROGRAM_DIR . '/' . $yeswikiBareAsset;
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
