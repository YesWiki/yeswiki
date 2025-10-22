<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\ApiService;

$entryManager = $this->services->get(EntryManager::class);
$apiService = $this->services->get(ApiService::class);

if ($entryManager->isEntry($this->GetPageTag())) {
    if ($this->hasAccess('write', $this->GetPageTag())) {
        $semantic = false; // strpos($_SERVER['CONTENT_TYPE'], 'application/ld+json') !== false;

        $_POST['id_fiche'] = $this->GetPageTag();
        $_POST['antispam'] = 1;

        $entry = $entryManager->update($this->GetPageTag(), $_POST, $semantic, true);
        http_response_code(200);
        echo json_encode($entry);
    } else {
        http_response_code(403);
    }
} else {
    // Aucune fiche bazar trouvée avec ce tag
    http_response_code(404);
}
