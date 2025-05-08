<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Bazar\Service\FieldFactory;
use YesWiki\Bazar\Field\TextField;

$entryManager = $this->services->get(EntryManager::class);
$pageManager = $this->services->get(PageManager::class);
$fieldFactory = $this->services->get(FieldFactory::class);

if ($pageManager->getPageType($this->GetPageTag()) === 'fiche_bazar' && $this->HasAccess('read')) {
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

if ($pageManager->getPageType($this->GetPageTag()) === 'form' && $this->HasAccess('read')) {
    $tab = json_decode($this->page['body'], true);
    foreach ($tab['fields'] as $key => $field) {
        ksort($field);
        $cname = "YesWiki\\Bazar\\Field\\".$field['field_type'];
        $wikifield = $cname::mapToFieldArray($field);
        $newfield = json_decode(json_encode($fieldFactory->create($wikifield)), true);
        ksort($newfield);
        dump($field, $newfield);
    }
}
