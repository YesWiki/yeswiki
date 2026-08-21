#!/usr/bin/env bash
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# ext-imap is not thread safe, so it cannot be in a ZTS build and the binary has no IMAP importer.
NOT_IN_A_THREADED_BINARY="imap"

# What `php -m` calls an extension, where that is not its composer name.
module_name() {
    case "$1" in
        opcache) printf 'Zend OPcache' ;;
        zend-opcache) printf 'Zend OPcache' ;;
        *) printf '%s' "$1" ;;
    esac
}

# Every extension the manifest names, required and suggested alike.
audited_extensions() {
    php -r '
        $manifest = json_decode(file_get_contents($argv[1]), true);
        foreach (array_merge(array_keys($manifest["require"]), array_keys($manifest["suggest"])) as $package) {
            if (str_starts_with($package, "ext-")) {
                echo substr($package, 4), " ";
            }
        }
    ' "$repo/composer.json"
}

main() {
    local binary="${1:?usage: check-binary.sh <binary>}"
    local modules missing=()
    modules="$("$binary" php-cli -m)"
    printf '%s\n' "$modules"

    for extension in $(audited_extensions); do
        case " $NOT_IN_A_THREADED_BINARY " in
            *" $extension "*) continue ;;
        esac
        if ! grep -qix -- "$(module_name "$extension")" <<< "$modules"; then
            missing+=("$extension")
        fi
    done

    if [ ${#missing[@]} -gt 0 ]; then
        printf 'missing from the binary, and a static binary can never add one: %s\n' "${missing[*]}" >&2
        exit 1
    fi

    printf '\nevery audited extension is compiled in\n'
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
