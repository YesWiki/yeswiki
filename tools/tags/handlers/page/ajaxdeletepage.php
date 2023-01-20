<?php

use YesWiki\Tags\Service\TagsManager;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$tagsManager = $this->services->get(TagsManager::class);

// on ne fait quelque chose uniquement dans le cas d'une requete jsonp
if (isset($_GET['jsonp_callback'])) {
    // on initialise la sortie:
    header('Content-type:application/json');
    if ($this->UserIsOwner() || $this->UserIsAdmin()) {
        $tag = $this->GetPageTag();
        $this->DeleteOrphanedPage($tag);
        $this->LogAdministrativeAction($this->GetUserName(), "Suppression de la page ->\"\"" . $tag . "\"\"");
        echo $_GET['jsonp_callback']."(".json_encode(array("reponse"=>mb_convert_encoding("succes", 'UTF-8', 'ISO-8859-1'))).")";
    } else {
        echo $_GET['jsonp_callback']."(".json_encode(array("reponse"=>mb_convert_encoding("interdit", 'UTF-8', 'ISO-8859-1'))).")";
    }
}
