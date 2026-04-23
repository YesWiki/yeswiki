<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\SemanticTransformer;
use YesWiki\Bazar\Service\FormManager;

$entryManager = $this->services->get(EntryManager::class);

if ($entryManager->isEntry($this->GetPageTag()) && $this->HasAccess('read')) {
    if (isset($_SERVER['HTTP_ACCEPT']) && (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false || strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false)) {
        $semantic = strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false;
        $contentType = $semantic ? 'application/ld+json' : 'application/json';

        header("Content-type: $contentType; charset=UTF-8");
        header('Access-Control-Allow-Origin: *');

        $fiche = $entryManager->getOne($this->GetPageTag());

        if ($semantic) {
            $form = $this->services->get(FormManager::class)->getOne($fiche['id_typeannonce']);
            $semanticFiche = $this->services->get(SemanticTransformer::class)->convertToSemanticData($form, $fiche);
            $this->exit(json_encode($semanticFiche));
        } else {
            $this->exit(json_encode($fiche));
        }
    } else {
        $this->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js', true, true);
    }
}
