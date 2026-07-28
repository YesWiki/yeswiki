<?php

use YesWiki\Content\Service\EntryManager;

$entryManager = $this->services->get(EntryManager::class);

if ($entryManager->isEntry($this->GetPageTag())) {
    $this->AddJavascriptFile('javascripts/bazar.js', true, true);
    $fiche = $entryManager->getOne($this->GetPageTag());
    $this->page['body'] = '""' . baz_voir_fiche(0, $fiche) . '""';
}
