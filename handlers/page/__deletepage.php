<?php

use YesWiki\Core\Service\CsrfTokenChecker;
use YesWiki\Core\Service\DbService;

if (($this->UserIsOwner() || $this->UserIsAdmin())
    && isset($_GET['eraselink'])
    && $_GET['eraselink'] === 'oui'
    && isset($_GET['confirme'])
    && ($_GET['confirme'] === 'oui')
) {
    try {
        if ($this->services->get(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false)) {
            $tag = $this->GetPageTag();
            $dbService = $this->services->get(DbService::class);
            $dbService->query("DELETE FROM {$dbService->prefixTable('links')} WHERE to_tag = '" . $dbService->escape($tag) . "'");
        }
    } catch (Throwable $th) {
        // do nothing
    }
}
