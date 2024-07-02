<?php

use YesWiki\Bazar\Service\EntryManager;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$entryManager = $this->services->get(EntryManager::class);

if ($entryManager->isEntry($this->GetPageTag()) && $this->HasAccess('read')) {
    if (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false || strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false) {
        $semantic = strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false;
        $contentType = $semantic ? 'application/ld+json' : 'application/json';

        header("Content-type: $contentType; charset=UTF-8");
        header('Access-Control-Allow-Origin: *');

        $fiche = $entryManager->getOne($this->GetPageTag(), $semantic);
        $this->exit(json_encode($fiche));
    } else {
        $this->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js');
    }
}
