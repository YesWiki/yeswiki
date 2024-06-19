<?php

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

if ($lines = file($text)) {
    foreach ($lines as $line) {
        // To avoid loop:ignore inclusion of other raw link
        if (!(preg_match("/\[\[\|(\S*)(\s+(.+))?\]\]/", $line, $matches))) {
            echo $line;
        }
    }
}
