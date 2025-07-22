<?php

// Administration

// Verification de securite
if (!defined('TOOLS_MANAGER')) {
    exit('accès direct interdit');
}

$buffer->str(
    '
Le plugin "Tags" vous permet de gerer des mots clés par page et ajoute des actions pour les consulter par flux RSS, moteur de recherche ou liste.
'
);
