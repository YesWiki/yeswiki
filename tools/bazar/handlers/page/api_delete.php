<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\ApiService;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$entryManager = $this->services->get(EntryManager::class);
$apiService = $this->services->get(ApiService::class);

if ($entryManager->isEntry($this->GetPageTag())) {
    if ($apiService->isAuthorized()) {
        $entryManager->delete($this->GetPageTag());
        http_response_code(204);
    } else {
        http_response_code(304);
    }
} else {
    // Aucune fiche bazar trouvée avec ce tag
    http_response_code(404);
}
