<?php

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

if ($this->HasAccess('read')) {
    if (!$this->page) {
        return;
    } else {
        header('Content-type: text/plain; charset=' . YW_CHARSET);
        // display raw page
        echo _convert($this->page['body'], YW_CHARSET);
    }
} else {
    return;
}
