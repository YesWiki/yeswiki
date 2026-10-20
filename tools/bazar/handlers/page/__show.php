<?php

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Bazar\Service\ActivityPubLog;
use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SemanticTransformer;

$entryManager = $this->services->get(EntryManager::class);
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$wantsActivity = strpos($accept, 'application/activity+json') !== false
    || (strpos($accept, 'application/ld+json') !== false && strpos($accept, 'activitystreams') !== false);

if ($wantsActivity && !$this->page) {
    $this->services->get(ActivityPubLog::class)->served(null, Request::createFromGlobals(), 404);
    http_response_code(404);
    header('Content-type: application/activity+json; charset=UTF-8');
    $this->exit(json_encode(['error' => 'Not found']));
}

if ($entryManager->isEntry($this->GetPageTag()) && $this->HasAccess('read')) {
    if ($wantsActivity) {
        $fiche = $entryManager->getOne($this->GetPageTag());
        $form = $this->services->get(FormManager::class)->getOne($fiche['id_typeannonce']);
        $activityPubService = $this->services->get(ActivityPubService::class);
        if ($activityPubService->hasActor($form)) {
            $this->services->get(ActivityPubLog::class)->served($fiche['id_typeannonce'], Request::createFromGlobals(), 200);
            header('Content-type: application/activity+json; charset=UTF-8');
            header('Access-Control-Allow-Origin: *');
            $this->exit(json_encode(
                ['@context' => 'https://www.w3.org/ns/activitystreams'] + $activityPubService->objectFor($form, $fiche),
                JSON_UNESCAPED_SLASHES
            ));
        }
    }
    if (strpos($accept, 'application/json') !== false || strpos($accept, 'application/ld+json') !== false) {
        $semantic = strpos($accept, 'application/ld+json') !== false;
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
