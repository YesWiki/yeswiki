<?php

/*
 * FrankenPHP worker entry point (ADR-0024): boots the wiki once and serves requests until its container is out of date,
 * checked as each request arrives (that request is sent back with a 307 for the restarted worker) and after each one.
 */

use YesWiki\Core\YesWikiLoader;
use YesWiki\Kernel\Service\AssetPublisher;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Probes;
use YesWiki\Kernel\Service\Queue\QueueRequestTrigger;

require_once __DIR__ . '/src/bootstrap_paths.php';
require_once __DIR__ . '/src/Kernel/Service/AssetPublisher.php';
require_once __DIR__ . '/src/Kernel/Service/Probes.php';
require_once __DIR__ . '/src/YesWikiLoader.php';

if (!function_exists('frankenphp_handle_request')) {
    error_log("worker.php is FrankenPHP's entry point and needs frankenphp_handle_request().");
    exit(1);
}

$wiki = YesWikiLoader::getWiki();
$queueTrigger = $wiki->services->get(QueueRequestTrigger::class);
$queueTrigger->servedByWorker();

$requestsBeforeRestart = (int)(getenv('YESWIKI_WORKER_REQUESTS') ?: 500);
$database = getenv('YESWIKI_DB_KEEP_CONNECTION') === '1' ? null : $wiki->services->get(DbService::class);
$served = 0;
$timeLimit = (int)ini_get('max_execution_time');

$probed = null;
$outOfDate = false;
$handler = static function () use ($wiki, $timeLimit, &$probed, &$outOfDate): void {
    foreach (['SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF'] as $describesTheScript) {
        if (isset($_SERVER[$describesTheScript]) && is_string($_SERVER[$describesTheScript])) {
            $_SERVER[$describesTheScript] = str_replace('worker.php', 'index.php', $_SERVER[$describesTheScript]);
        }
    }

    AssetPublisher::interceptAssetRequest();

    $probed = Probes::asked();
    if ($probed === Probes::LIVENESS) {
        Probes::live();

        return;
    }
    if ($probed === Probes::READINESS) {
        Probes::ready();

        return;
    }

    if ($wiki->cannotServeTheNextRequest()) {
        $outOfDate = true;
        header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/'), true, 307);
        header('Retry-After: 0');
        header('Cache-Control: no-store');

        return;
    }

    try {
        $wiki->run();
    } finally {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit($timeLimit);
        }
    }
};

while ($served < $requestsBeforeRestart) {
    $database?->disconnectIfIdle();
    $probed = null;
    $keepServing = frankenphp_handle_request($handler);
    if ($outOfDate) {
        error_log('the compiled container is out of date; sent the request back and restarting this worker');
        break;
    }
    if ($probed === null) {
        $queueTrigger->afterResponse();
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit($timeLimit);
    }
    if (!$keepServing) {
        break;
    }
    $served++;

    if ($wiki->containerCacheIsGone() || $wiki->configurationChanged() || $wiki->templatesChanged()) {
        error_log('the container went out of date during this request; restarting this worker');
        break;
    }

    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}
