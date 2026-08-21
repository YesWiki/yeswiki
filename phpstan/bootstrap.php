<?php

/**
 * PHPStan bootstrap.
 *
 * Defines the constants the codebase expects to exist, without the side effects of the
 * real boot path. `src/bootstrap_paths.php` cannot be used directly here because it also
 * provisions cache/custom/files/private directories on load, which analysis must not do.
 *
 * `src/constants.php` is pure `define()` calls and is loaded as-is.
 */
if (!defined('YESWIKI_PROGRAM_DIR')) {
    define('YESWIKI_PROGRAM_DIR', \dirname(__DIR__));
}
if (!defined('YESWIKI_INSTANCE_DIR')) {
    define('YESWIKI_INSTANCE_DIR', \dirname(__DIR__));
}

require_once \dirname(__DIR__) . '/src/constants.php';

// defined at runtime by LanguageService::initialize() from the wiki's charset config
if (!defined('YW_CHARSET')) {
    define('YW_CHARSET', 'UTF-8');
}

// zebra_image defines its constants at file-load time; whether PHPStan has seen the file
// depends on discovery order, making 'constant not found' errors flap between runs
if (!defined('ZEBRA_IMAGE_NOT_BOXED')) {
    define('ZEBRA_IMAGE_NOT_BOXED', 1);
}
