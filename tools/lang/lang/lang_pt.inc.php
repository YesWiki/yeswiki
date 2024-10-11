<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], [
    // Action translation
    'LANG_DESTINATION_REQUIRED' => 'O parâmetro de destino (idioma de destino), obrigatório, está faltando.',
    'LANG_FLAG_FILE_MISSING' => 'Falta bandeira para este país',
]);
