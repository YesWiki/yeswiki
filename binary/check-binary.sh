#!/usr/bin/env bash
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# ext-imap is not thread safe, so it cannot be in a ZTS build and the binary has no IMAP importer.
NOT_IN_A_THREADED_BINARY="imap"

# What the binary calls an extension, where that is not its composer name. `php-cli` takes a
# script or `-r` and reads any other flag as a filename, so `-m` is not available here.
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

# What the manifest says this binary was built from. Empty for a binary built any other way.
stated_build() {
    "$1" version --build 2>/dev/null || true
}

# A binary that disagrees with its own manifest is worse than one with no manifest: the repository
# index serves that manifest, so an upgrade would be decided on a claim nothing checked.
check_against_its_manifest() {
    local binary="$1" modules="$2" manifest="$3"
    local disagreements=()

    local statedPhp actualPhp
    statedPhp="$(php -r '$b = json_decode(file_get_contents("php://stdin"), true); echo $b["php"] ?? "";' <<< "$manifest")"
    actualPhp="$("$binary" php-cli -r 'echo PHP_VERSION;')"
    if [ "$statedPhp" != "$actualPhp" ]; then
        disagreements+=("manifest says php $statedPhp, the binary runs $actualPhp")
    fi

    local extension
    for extension in $(php -r '$b = json_decode(file_get_contents("php://stdin"), true); echo implode(" ", $b["extensions"] ?? []);' <<< "$manifest"); do
        if ! grep -qix -- "$(module_name "$extension")" <<< "$modules"; then
            disagreements+=("manifest names $extension, which is not loaded")
        fi
    done

    if [ ${#disagreements[@]} -gt 0 ]; then
        printf 'the binary and its manifest disagree:\n' >&2
        printf '  %s\n' "${disagreements[@]}" >&2
        exit 1
    fi

    printf 'and it agrees with the manifest it carries (php %s, %s)\n' \
        "$actualPhp" \
        "$(php -r '$b = json_decode(file_get_contents("php://stdin"), true); echo $b["version"] ?? "?", " ", substr($b["commit"] ?? "", 0, 9);' <<< "$manifest")"
}

main() {
    local binary="${1:?usage: check-binary.sh <binary>}"
    local modules missing=()
    modules="$("$binary" php-cli -r 'echo implode(PHP_EOL, get_loaded_extensions());')"
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

    local manifest
    manifest="$(stated_build "$binary")"
    if [ -z "$manifest" ]; then
        printf 'no build manifest: this binary did not come from build-static.sh\n' >&2
        exit 1
    fi
    check_against_its_manifest "$binary" "$modules" "$manifest"
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
