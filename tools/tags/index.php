<?php

// Administration

// Verification de securite
if (!defined('TOOLS_MANAGER')) {
    { file_put_contents('/tmp/direct_access_trace.txt', __FILE__ . chr(10) . (new Exception())->getTraceAsString()); exit('BLOCKED_MARKER_XYZ: ' . __FILE__); }
}

$buffer->str(
    '
Le plugin "Tags" vous permet de gerer des mots clés par page et ajoute des actions pour les consulter par flux RSS, moteur de recherche ou liste.
'
);
