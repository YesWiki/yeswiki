<?php

/**
 * Regenerates src/lang/javascript-keys.php: every key the shipped JavaScript asks `_t()`
 * for.
 *
 * `_t()` in the browser reads the javascript catalog (`yeswikijs_*.php`) and nothing else,
 * so a key that lives only in the PHP catalog renders as its own name. Rather than keep two
 * hand-written catalogs in step -- which they were not: 255 of the 335 keys the scripts ask
 * for were missing -- LanguageService projects the PHP catalog onto this list at load time.
 *
 * Run after adding a `_t('SOMETHING')` to a script:
 *
 *     php src/build-js-lang-keys.php
 *
 * JsLangKeysTest fails if the checked-in list has drifted from the scripts.
 */
require_once __DIR__ . '/lang/javascript-keys-builder.php';

$path = __DIR__ . '/lang/javascript-keys.php';
file_put_contents($path, renderJavascriptKeysFile(collectJavascriptTranslationKeys(dirname(__DIR__) . '/javascripts')));

echo "wrote $path\n";
