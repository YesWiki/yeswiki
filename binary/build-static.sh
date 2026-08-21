#!/usr/bin/env bash
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

FRANKENPHP_VERSION="${FRANKENPHP_VERSION:-1.12.7}"
PHP_VERSION="${PHP_VERSION:-8.4}"
TARGETARCH="${TARGETARCH:-$(uname -m)}"
COMPRESS="${COMPRESS:-1}"
DEBUG_SYMBOLS="${DEBUG_SYMBOLS:-}"
OUTPUT="${OUTPUT:-$repo/binary/dist}"
FRANKENPHP_SRC="${FRANKENPHP_SRC:-$repo/binary/.frankenphp}"
BUILD_PROXY="${BUILD_PROXY:-${HTTPS_PROXY:-${https_proxy:-}}}"

# Extensions PHP always compiles in, which static-php-cli therefore does not name.
ALWAYS_COMPILED_IN="hash json pcre"

# Optional in composer.json, required here: a static binary cannot add one later.
ALWAYS_IN_THE_BINARY="opcache pdo_mysql pdo_pgsql pdo_sqlite intl"

# Extensions that cannot be in a threaded build, whatever the manifest says. FrankenPHP is ZTS by
# construction, and static-php-cli refuses ext-imap there: "not thread safe, do not build it with
# ZTS builds". The IMAP importer therefore does not exist in the binary deployment.
IMPOSSIBLE_IN_A_THREADED_BINARY="imap"

# What gd is built against, matching docker/dockerfile.
EXTENSION_LIBS="freetype,libjpeg,libwebp,libavif"

# Caddy modules. Setting XCADDY_ARGS at all drops the three FrankenPHP includes by default,
# so they are named again here, next to ours.
CADDY_MODULES=(
    "--with github.com/dunglas/caddy-cbrotli"
    "--with github.com/dunglas/mercure/caddy"
    "--with github.com/dunglas/vulcain/caddy"
    "--with github.com/YesWiki/yeswiki/binary/caddy=$repo/binary"
)

# The extension set, read out of composer.json so it cannot drift from the audit (ticket 47).
php_extensions() {
    local declared
    declared="$(php -r '
        $manifest = json_decode(file_get_contents($argv[1]), true);
        $names = [];
        foreach (array_merge(array_keys($manifest["require"]), array_keys($manifest["suggest"])) as $package) {
            if (str_starts_with($package, "ext-")) {
                $names[] = substr($package, 4);
            }
        }
        echo implode(" ", $names);
    ' "$repo/composer.json")"

    local extensions=()
    for name in $declared $ALWAYS_IN_THE_BINARY; do
        name="${name/zend-opcache/opcache}"
        case " $ALWAYS_COMPILED_IN $IMPOSSIBLE_IN_A_THREADED_BINARY " in
            *" $name "*) continue ;;
        esac
        case " ${extensions[*]-} " in
            *" $name "*) continue ;;
        esac
        extensions+=("$name")
    done

    printf '%s\n' "$(IFS=,; echo "${extensions[*]}")"
}

main() {
    local extensions
    extensions="$(php_extensions)"

    printf 'frankenphp %s, php %s, %s\n' "$FRANKENPHP_VERSION" "$PHP_VERSION" "$TARGETARCH"
    printf 'extensions: %s\n' "$extensions"
    printf 'extension libs: %s\n' "$EXTENSION_LIBS"

    "$repo/binary/build-program.sh" >/dev/null

    if [ ! -d "$FRANKENPHP_SRC/.git" ]; then
        rm -rf "$FRANKENPHP_SRC"
        git clone --depth 1 --branch "v${FRANKENPHP_VERSION}" https://github.com/php/frankenphp.git "$FRANKENPHP_SRC"
    fi
    git -C "$FRANKENPHP_SRC" fetch --depth 1 origin "refs/tags/v${FRANKENPHP_VERSION}:refs/tags/v${FRANKENPHP_VERSION}" >/dev/null 2>&1 || true
    git -C "$FRANKENPHP_SRC" checkout --quiet "v${FRANKENPHP_VERSION}"

    local image="yeswiki-static-builder-${TARGETARCH}"
    local golang_base network=()
    golang_base="$(cd "$FRANKENPHP_SRC" && docker buildx bake --print static-builder-musl 2>/dev/null \
        | php -r '$j = json_decode(stream_get_contents(STDIN), true); echo $j["target"]["static-builder-musl"]["contexts"]["golang-base"];')"

    if [ -n "$BUILD_PROXY" ]; then
        printf 'downloads go through %s\n' "$BUILD_PROXY"
        network=(--network=host --allow=network.host
            --build-arg "HTTP_PROXY=${BUILD_PROXY}"
            --build-arg "HTTPS_PROXY=${BUILD_PROXY}"
            --build-arg "http_proxy=${BUILD_PROXY}"
            --build-arg "https_proxy=${BUILD_PROXY}"
            --build-arg "NO_PROXY=localhost,127.0.0.1")
    fi

    ( cd "$FRANKENPHP_SRC" && GITHUB_TOKEN="${GITHUB_TOKEN:-}" docker buildx build --load \
        --file static-builder-musl.Dockerfile \
        --build-context "golang-base=${golang_base}" \
        --secret id=github-token,env=GITHUB_TOKEN \
        --tag "${image}" \
        --platform "linux/${TARGETARCH}" \
        ${network[@]+"${network[@]}"} \
        --build-arg "FRANKENPHP_VERSION=${FRANKENPHP_VERSION}" \
        --build-arg "PHP_VERSION=${PHP_VERSION}" \
        --build-arg "PHP_EXTENSIONS=${extensions}" \
        --build-arg "PHP_EXTENSION_LIBS=${EXTENSION_LIBS}" \
        --build-arg "XCADDY_ARGS=${CADDY_MODULES[*]}" \
        --build-arg "COMPRESS=${COMPRESS}" \
        --build-arg "DEBUG_SYMBOLS=${DEBUG_SYMBOLS}" \
        . )

    mkdir -p "$OUTPUT"
    local container
    container="$(docker create "$image")"
    docker cp "$container:/go/src/app/dist/frankenphp-linux-${TARGETARCH}" "$OUTPUT/yeswiki-linux-${TARGETARCH}"
    docker rm "$container" >/dev/null

    chmod +x "$OUTPUT/yeswiki-linux-${TARGETARCH}"
    printf 'built %s\n' "$OUTPUT/yeswiki-linux-${TARGETARCH}"
    ls -lh "$OUTPUT/yeswiki-linux-${TARGETARCH}"
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
