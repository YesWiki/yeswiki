<?php

use YesWiki\Tags\Service\TagsManager;

$tagsManager = $this->services->get(TagsManager::class);

$response = [];
$tab_tous_les_tags = $tagsManager->getAll();
if (is_array($tab_tous_les_tags)) {
    foreach ($tab_tous_les_tags as $tab_les_tags) {
        $tab_les_tags['value'] = $tab_les_tags['value'];
        $response[] = [$tab_les_tags['value'], $tab_les_tags['value'], null, $tab_les_tags['value']];
    }
}
sort($response);

header('Content-type: application/json');
echo json_encode($response);
