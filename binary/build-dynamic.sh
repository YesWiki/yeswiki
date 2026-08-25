#!/usr/bin/env bash
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

FRANKENPHP_VERSION="${FRANKENPHP_VERSION:-1.12.7}"
OUTPUT="${OUTPUT:-$repo/binary/dist}"
BINARY="${BINARY:-yeswiki-dev}"

# shellcheck source=binary/build-manifest.sh
. "$(dirname "${BASH_SOURCE[0]}")/build-manifest.sh"

main() {
    if ! command -v php-config >/dev/null; then
        printf 'php-config is not on PATH: this build needs a libphp to link against.\n' >&2
        printf 'On NixOS: nix-shell binary/dev-shell.nix --run ./binary/build-dynamic.sh\n' >&2
        exit 1
    fi

    local zts
    zts="$(php-config --php-binary >/dev/null 2>&1 && "$(php-config --php-binary)" -r 'echo PHP_ZTS ? "yes" : "no";' || echo unknown)"
    if [ "$zts" != "yes" ]; then
        printf 'this php is not ZTS (thread safety: %s), and worker mode needs it.\n' "$zts" >&2
        exit 1
    fi

    printf 'php %s, frankenphp %s, dynamic\n' "$(php-config --version)" "$FRANKENPHP_VERSION"
    printf 'linking against %s\n' "$(php-config --prefix)"

    "$repo/binary/build-program.sh" >/dev/null

    # The same manifest the shipped binary carries, so `version --build` answers here too and a
    # dev build cannot be mistaken for a release one.
    #
    # The extension list is left EMPTY on purpose. A static build compiles its extensions in, so
    # the list is a build input and stating it is the whole point. This one links the machine's
    # libphp and resolves extensions at runtime -- and on a ZTS build it usually resolves none,
    # because distribution .so files are compiled against non-ZTS PHP and fail with
    # `undefined symbol: executor_globals`. Listing what the machine's *CLI* has would be a
    # manifest that names extensions this binary cannot load, which is worse than naming none.
    write_build_manifest "$repo" "$FRANKENPHP_VERSION" "$(php-config --version)" "$(uname -m)" \
        "" "" "" "0" "dynamic-dev" \
        > "$repo/binary/program/BUILD.json"

    mkdir -p "$OUTPUT"

    CGO_ENABLED=1 \
    XCADDY_GO_BUILD_FLAGS="-ldflags='-w -s' -tags=nobadger,nomysql,nopgx" \
    CGO_CFLAGS="$(php-config --includes)" \
    CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
        xcaddy build \
        --output "$OUTPUT/$BINARY" \
        --with "github.com/dunglas/frankenphp@v${FRANKENPHP_VERSION}" \
        --with "github.com/dunglas/frankenphp/caddy@v${FRANKENPHP_VERSION}" \
        --with github.com/dunglas/caddy-cbrotli \
        --with github.com/dunglas/mercure/caddy \
        --with github.com/dunglas/vulcain/caddy \
        --with "github.com/YesWiki/yeswiki/binary=$repo/binary"

    printf 'built %s\n' "$OUTPUT/$BINARY"
    "$OUTPUT/$BINARY" version

    local loaded
    loaded="$("$OUTPUT/$BINARY" php-cli -r 'echo count(get_loaded_extensions());' 2>/dev/null || echo 0)"
    printf 'its php has %s extensions (built in only -- this build loads no .so files)\n' "$loaded"
    printf 'that is enough to serve pages and not enough to install a wiki: `make binary` for that.\n'
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
