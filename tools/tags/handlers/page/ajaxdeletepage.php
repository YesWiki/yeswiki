<?php

use YesWiki\Core\Controller\PageController;
use YesWiki\Tags\Service\TagsManager;

$tagsManager = $this->services->get(TagsManager::class);

// on ne fait quelque chose uniquement dans le cas d'une requete jsonp
if (isset($_GET['jsonp_callback'])) {
    // on initialise la sortie:
    header('Content-type:application/json');
    if ($this->UserIsOwner() || $this->UserIsAdmin()) {
        $tag = $this->GetPageTag();

        $this->services->get(PageController::class)->delete($tag);
        echo $_GET['jsonp_callback'] . '(' . json_encode(['reponse' => mb_convert_encoding('succes', 'UTF-8', 'ISO-8859-1')]) . ')';
    } else {
        echo $_GET['jsonp_callback'] . '(' . json_encode(['reponse' => mb_convert_encoding('interdit', 'UTF-8', 'ISO-8859-1')]) . ')';
    }
}
