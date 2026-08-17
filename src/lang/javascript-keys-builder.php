<?php

/**
 * Scan the shipped scripts for the translation keys they ask `_t()` for.
 *
 * @return list<string> sorted, unique
 */
function collectJavascriptTranslationKeys(string $javascriptDir): array
{
    $keys = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($javascriptDir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'js' || str_contains($file->getPathname(), '/vendor/')) {
            continue;
        }
        if (preg_match_all('/_t\(\s*[\'"]([A-Z0-9_]+)[\'"]/', (string)file_get_contents($file->getPathname()), $matches)) {
            $keys = array_merge($keys, $matches[1]);
        }
    }
    $keys = array_values(array_unique($keys));
    sort($keys);

    return $keys;
}

/**
 * @param list<string> $keys
 */
function renderJavascriptKeysFile(array $keys): string
{
    $lines = array_map(fn (string $key) => "    '$key',", $keys);

    return "<?php\n\n"
        . "/**\n"
        . " * Translation keys the shipped JavaScript asks `_t()` for.\n"
        . " *\n"
        . " * GENERATED -- run `php src/build-js-lang-keys.php` after adding a `_t()` call to a\n"
        . " * script. LanguageService copies each of these from the PHP catalog into the javascript\n"
        . " * one, which is the only catalog `_t()` reads in the browser.\n"
        . " */\n\n"
        . "return [\n" . implode("\n", $lines) . "\n];\n";
}
