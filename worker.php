<?php

/*
 * FrankenPHP worker entry point (ADR-0024): boots the wiki once and serves requests until its container is cleared.
 */

use YesWiki\Core\YesWikiLoader;
use YesWiki\Kernel\Service\AssetPublisher;
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
$served = 0;
$timeLimit = (int)ini_get('max_execution_time');

$probed = null;
$handler = static function () use ($wiki, $timeLimit, &$probed): void {
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
    if ($wiki->containerCacheIsGone() || $wiki->configurationChanged() || $wiki->templatesChanged()) {
        error_log('the compiled container is out of date; restarting this worker');
        break;
    }

    $probed = null;
    $keepServing = frankenphp_handle_request($handler);
    if ($probed === null) {
        $queueTrigger->afterResponse();
    }
    // the queue ran on this request's time limit, and raised it: the next request gets its own
    if (function_exists('set_time_limit')) {
        @set_time_limit($timeLimit);
    }
    if (!$keepServing) {
        break;
    }
    $served++;

    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}
