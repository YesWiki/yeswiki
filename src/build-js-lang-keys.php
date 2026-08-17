<?php

require_once __DIR__ . '/lang/javascript-keys-builder.php';

$path = __DIR__ . '/lang/javascript-keys.php';
file_put_contents($path, renderJavascriptKeysFile(collectJavascriptTranslationKeys(dirname(__DIR__) . '/javascripts')));

echo "wrote $path\n";
