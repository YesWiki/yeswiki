<?php

if (!defined("WIKINI_VERSION")) {
    die ("acc&egrave;s direct interdit");
}

global $bazarFiche;

$type = $this->GetTripleValue($this->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/type', '', '');
if ($type == 'fiche_bazar') {
    if ($this->api->isAuthorized()) {
        $bazarFiche->delete($this->GetPageTag());
        http_response_code(204);
    } else {
        http_response_code(304);
    }
} else {
    // Aucune fiche bazar trouvée avec ce tag
    http_response_code(404);
}
