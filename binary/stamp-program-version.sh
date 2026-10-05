#!/usr/bin/env bash
# Write a version into a published Program's composer.json (ADR-0029): the repository states none.
# Usage: stamp-program-version.sh <composer.json> <tag or git describe output>; a leading "v" is dropped.
set -euo pipefail

manifest="$1"
version="${2#v}"

if [ -z "$version" ]; then
    printf 'no version given, %s stays a dev Program\n' "$manifest"
    exit 0
fi

php -r '
    $manifest = json_decode((string)file_get_contents($argv[1]));
    if (!$manifest instanceof stdClass) {
        fwrite(STDERR, $argv[1] . " is not a JSON object\n");
        exit(1);
    }
    $manifest->version = $argv[2];
    file_put_contents($argv[1], json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
' "$manifest" "$version"
