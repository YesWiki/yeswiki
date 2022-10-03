<?php

use YesWiki\Bazar\Service\EntryManager;

$entryManager = $this->services->get(EntryManager::class);

if ($entryManager->isEntry($this->GetPageTag())) {
    $this->AddJavascriptFile('tools/bazar/libs/bazar.js');
    $fiche = $entryManager->getOne($this->GetPageTag());
    $this->page["body"] = '""'.baz_voir_fiche(0, $fiche).'""';
}
