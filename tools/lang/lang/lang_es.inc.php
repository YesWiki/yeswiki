<?php

if (!defined('WIKINI_VERSION')) {
    exit('acceso directo prohibido');
}

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], [
    // Action translation
    'LANG_DESTINATION_REQUIRED' => 'Falta el parámetro destinación (idioma destinación) obligatorio.',
    'LANG_FLAG_FILE_MISSING' => 'Falta la bandera para este país',
]);
