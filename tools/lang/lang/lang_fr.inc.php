<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], [
    // Action translation
    'LANG_DESTINATION_REQUIRED' => 'Le param&egrave;tre destination (langue destination), obligatoire, est manquant.',
    'LANG_FLAG_FILE_MISSING' => 'Drapeau absent pour ce pays',
]);
