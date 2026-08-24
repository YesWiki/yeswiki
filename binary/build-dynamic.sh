#!/usr/bin/env bash
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

FRANKENPHP_VERSION="${FRANKENPHP_VERSION:-1.12.7}"
OUTPUT="${OUTPUT:-$repo/binary/dist}"
BINARY="${BINARY:-yeswiki-dev}"

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
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
