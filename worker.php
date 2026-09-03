<?php

/**
 * FrankenPHP worker entry point (ADR-0024).
 *
 * The wiki is booted once and then serves request after request, instead of being rebuilt for
 * each one. Everything that must not outlive a request is reset by `RequestScope` at the top of
 * `YesWikiRuntime::doRun()`, which is why this loop can be as short as it is.
 *
 * The wiki reads its own entry point out of `$_SERVER` to work out which page was asked for, and
 * under worker mode that is `worker.php` rather than `index.php` -- so every request rendered a
 * page of that name. The script is described to the wiki as the front controller it would have
 * been under php-fpm, which is what makes the two modes agree (single-binary 07).
 *
 * A worker also outlives its own compiled container, which lives in `cache/`: the loop below stops
 * when that has been cleared underneath it, rather than answering every remaining request with a
 * missing-service error.
 *
 * The session is the exception `RequestScope` cannot own: it belongs to PHP's session extension
 * rather than to a service, and under php-fpm the process ending is what closed it. A worker
 * outlives the request, so it is closed here or the next request finds one already open and reads
 * the last visitor's `$_SESSION` (single-binary 07).
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
    // Someone emptied cache/container while this worker was holding one. It cannot build another
    // service for the rest of its life, so it stops here and FrankenPHP starts one that can.
    if ($wiki->containerCacheIsGone()) {
        fwrite(STDERR, "the compiled container was cleared; restarting this worker\n");
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
