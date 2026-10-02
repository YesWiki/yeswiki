<?php

use YesWiki\Bazar\Service\EntryManager;

$entryManager = $this->services->get(EntryManager::class);

if (!$this->GetUser()) {
    http_response_code(401);
} elseif ($entryManager->isEntry($this->GetPageTag())) {
    if ($this->hasAccess('write', $this->GetPageTag())) {
        $semantic = strpos($_SERVER['CONTENT_TYPE'], 'application/ld+json') !== false;

        $entryManager->update($this->GetPageTag(), $_POST, $semantic, false);
        http_response_code(204);
    } else {
        http_response_code(403);
    }
} else {
    http_response_code(404);
}
