<?php

if (!defined("WIKINI_VERSION")) {
    die ("acc&egrave;s direct interdit");
}

$ficheManager = $this->services->get('bazar.fiche.manager');

if ($ficheManager->isFiche($this->GetPageTag())) {
    if ($this->api->isAuthorized()) {
        $semantic = strpos($_SERVER['CONTENT_TYPE'], 'application/ld+json') !== false;

        $_POST['id_fiche'] = $this->GetPageTag();
        $_POST['antispam'] = 1;

        $ficheManager->update($this->GetPageTag(), $_POST, $semantic, false);
        http_response_code(204);
    } else {
        http_response_code(304);
    }
} else {
    // Aucune fiche bazar trouvée avec ce tag
    http_response_code(404);
}
