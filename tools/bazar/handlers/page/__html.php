<?php

use YesWiki\Bazar\Service\EntryManager;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$entryManager = $this->services->get(EntryManager::class);

if ($entryManager->isEntry($this->GetPageTag())) {
    $this->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js');
    $fiche = $entryManager->getOne($this->GetPageTag());
    $this->page['body'] = '""' . baz_voir_fiche(0, $fiche) . '""';
}
