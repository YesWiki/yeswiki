<?php

/*
 * FrankenPHP worker entry point (ADR-0024): boots the wiki once and serves requests until its container is cleared.
 */

use YesWiki\Core\YesWikiLoader;
use YesWiki\Kernel\Service\AssetPublisher;

require_once __DIR__ . '/src/bootstrap_paths.php';
require_once __DIR__ . '/src/Kernel/Service/AssetPublisher.php';
require_once __DIR__ . '/src/YesWikiLoader.php';

if (!function_exists('frankenphp_handle_request')) {
    error_log("worker.php is FrankenPHP's entry point and needs frankenphp_handle_request().");
    exit(1);
}

$wiki = YesWikiLoader::getWiki();

$requestsBeforeRestart = (int)(getenv('YESWIKI_WORKER_REQUESTS') ?: 500);
$served = 0;

$handler = static function () use ($wiki): void {
    foreach (['SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF'] as $describesTheScript) {
        if (isset($_SERVER[$describesTheScript]) && is_string($_SERVER[$describesTheScript])) {
            $_SERVER[$describesTheScript] = str_replace('worker.php', 'index.php', $_SERVER[$describesTheScript]);
        }
    }

    AssetPublisher::interceptAssetRequest();

    try {
        $wiki->run();
    } finally {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
};

while ($served < $requestsBeforeRestart) {
    if ($wiki->containerCacheIsGone() || $wiki->configurationChanged() || $wiki->templatesChanged()) {
        error_log('the compiled container is out of date; restarting this worker');
        break;
    }

    if (!frankenphp_handle_request($handler)) {
        break;
    }
    $served++;

    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}
