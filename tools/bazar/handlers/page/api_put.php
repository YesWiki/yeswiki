<?php

use YesWiki\Bazar\Service\EntryManager;

$entryManager = $this->services->get(EntryManager::class);

if (!$this->GetUser()) {
    http_response_code(401);
} elseif ($entryManager->isEntry($this->GetPageTag())) {
    if ($this->hasAccess('write', $this->GetPageTag())) {
        $semantic = false;

        $_POST['id_fiche'] = $this->GetPageTag();

        $entry = $entryManager->update($this->GetPageTag(), $_POST, $semantic, true);
        http_response_code(200);
        echo json_encode($entry);
    } else {
        http_response_code(403);
    }
} else {
    http_response_code(404);
}
