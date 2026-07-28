<?php

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\SemanticTransformer;

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
            $form = $this->services->get(FormManager::class)->getOne($fiche['form_id']);
            $semanticFiche = $this->services->get(SemanticTransformer::class)->convertToSemanticData($form, $fiche);
            $this->exit(json_encode($semanticFiche));
        } else {
            $this->exit(json_encode($fiche));
        }
    } else {
        $this->AddJavascriptFile('javascripts/bazar.js', true, true);
    }
}

// Verification de securite
$this->addJavascriptFile('javascripts/tag.js');

// Page translation (formerly tools/lang's __show before-callback): keep only the
// {{lang="xx"}} section matching the visitor's language, if the page uses markers
require_once YESWIKI_SOURCE_DIR . '/src/lang.functions.php';
if (!empty($this->page['body'])) {
    $this->page['body'] = filterBodyByLanguage(
        $this->page['body'],
        $GLOBALS['prefered_language'],
        $this->config['default_language']
    );
}
