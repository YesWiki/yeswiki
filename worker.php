<?php

/**
 * FrankenPHP worker entry point (ADR-0024).
 *
 * The wiki is booted once and then serves request after request, instead of being rebuilt for
 * each one. Everything that must not outlive a request is reset by `RequestScope` at the top of
 * `YesWikiRuntime::doRun()`, which is why this loop can be as short as it is.
 */

use YesWiki\Core\YesWikiLoader;
use YesWiki\Kernel\Service\AssetPublisher;

require_once __DIR__ . '/src/bootstrap_paths.php';
require_once __DIR__ . '/src/Kernel/Service/AssetPublisher.php';
require_once __DIR__ . '/src/YesWikiLoader.php';

if (!function_exists('frankenphp_handle_request')) {
    fwrite(STDERR, "worker.php is FrankenPHP's entry point and needs frankenphp_handle_request().\n");
    exit(1);
}

$wiki = YesWikiLoader::getWiki();

/** How many requests one worker serves before it is replaced, so a slow leak cannot accumulate. */
$requestsBeforeRestart = (int)(getenv('YESWIKI_WORKER_REQUESTS') ?: 500);
$served = 0;

$handler = static function () use ($wiki): void {
    AssetPublisher::interceptAssetRequest();
    $wiki->run();
};

while ($served < $requestsBeforeRestart) {
    if (!frankenphp_handle_request($handler)) {
        break;
    }
    $served++;

    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}
