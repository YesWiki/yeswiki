<?php

namespace YesWiki\Import\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * `{{sync}}`: a button that imports every configured data source, and shows what happened.
 *
 * The import runs in a console process rather than in this request. Not for speed -- this
 * page waits for it either way -- but because `importer:sync` is where an import is defined
 * to happen: the same entry point as the cron, the api and the automatic sync, so what an
 * admin reads here is what those do.
 */
class SyncAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'sync';
    }

    public function run(): string
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => static::performableName() . ' : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }

        $output = null;
        $returnCode = null;

        if ($this->getRequest()->request->has('sync')) {
            // syncing every source can take far longer than a page: remote wikis, whole
            // forms, entries and lists. This is a one-off an admin asked for and is waiting
            // on, so the regular request time limit is the wrong bound for it.
            set_time_limit(0);
            $result = $this->getService(ConsoleService::class)->startConsoleSync('importer:sync', [], '', 3600);
            $output = $result === null
                ? _t('IMPORTER_SYNC_NO_CONSOLE')
                : trim(($result[0]['stdout'] ?? '') . "\n" . ($result[0]['stderr'] ?? ''));
            $returnCode = $result === null ? 1 : 0;
        }

        return $this->render('@core/importer-sync.twig', [
            'currentUrl' => $this->getService(UrlFormatter::class)->href(),
            'output' => $output,
            'returnCode' => $returnCode,
        ]);
    }
}
