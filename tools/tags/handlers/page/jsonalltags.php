<?php

use YesWiki\Tags\Service\TagsManager;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$tagsManager = $this->services->get(TagsManager::class);

$response = [];
$tab_tous_les_tags = $tagsManager->getAll();
if (is_array($tab_tous_les_tags)) {
    foreach ($tab_tous_les_tags as $tab_les_tags) {
        $tab_les_tags['value'] = _convert($tab_les_tags['value'], 'ISO-8859-1');
        $response[] = [$tab_les_tags['value'], $tab_les_tags['value'], null, $tab_les_tags['value']];
    }
}
sort($response);

header('Content-type: application/json');
echo json_encode($response);
