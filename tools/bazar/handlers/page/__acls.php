<?php

use YesWiki\Bazar\Service\EntryManager;

if ($this->page && ($this->UserIsOwner() || $this->UserIsAdmin()) && $_POST && ($newowner__acls = $_POST["newowner"]) && $this->HasAccess('write')) {
    $entryManager = $this->services->get(EntryManager::class);
    $entryTag = $this->GetPageTag() ; 
    if ($entryManager->isEntry($entryTag)){
        $entry = $entryManager->getOne($entryTag) ;
        if (isset($entry['createur'])) {
            $entry['createur'] = $newowner__acls ;
            $entry['antispam'] = 1 ;
            $this->SetPageOwner($entryTag, $newowner__acls);
            $entryManager->update($entryTag,$entry) ;
        }
    }
}