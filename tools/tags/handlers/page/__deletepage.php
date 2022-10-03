<?php

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if (($this->UserIsOwner() || $this->UserIsAdmin())
        && isset($_GET['eraselink'])
        && $_GET['eraselink'] === 'oui'
        && isset($_GET['confirme'])
        && ($_GET['confirme'] === 'oui')
    ) {
    $inputToken = filter_input(INPUT_POST, 'csrf-token', FILTER_UNSAFE_RAW);
    $inputToken = in_array($inputToken,[false,null],true) ? $inputToken : htmlspecialchars(strip_tags($inputToken));
    if (!is_null($inputToken) && $inputToken !== false) {
        $tag = $this->GetPageTag();
        $token = new CsrfToken("handler\deletepage\\$tag", $inputToken);
        if ($this->services->get(CsrfTokenManager::class)->isTokenValid($token)) {
            $this->Query("DELETE FROM {$this->config["table_prefix"]}links WHERE to_tag = '$tag'");
        }
    }
}
