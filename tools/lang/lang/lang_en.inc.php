<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], [
    // Action translation
    'LANG_DESTINATION_REQUIRED' => 'Missing parameter destination (destination lang)',
    'LANG_FLAG_FILE_MISSING' => 'No flag for this country',
]);
