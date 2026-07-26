<?php

use YesWiki\Core\Service\EntryManager;
use YesWiki\Core\Service\FormManager;
use YesWiki\Core\Service\SemanticTransformer;

// relocated from tools/bazar/handlers/page/__show.php (ticket 24): if the page is a bazar
// entry and was requested with an Accept header asking for JSON/JSON-LD, respond with the
// entry's data directly instead of rendering the page as HTML.
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

// Verification de securite
$this->addJavascriptFile('javascripts/tag.js');
