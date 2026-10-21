#!/usr/bin/env bash
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

FRANKENPHP_VERSION="${FRANKENPHP_VERSION:-1.13.1}"
PHP_VERSION="${PHP_VERSION:-8.5.12}"
TARGETARCH="${TARGETARCH:-$(uname -m)}"
case "${COMPRESS:-1}" in
    ''|0|no|false|off) COMPRESS='' ;;
    *) COMPRESS=1 ;;
esac
DEBUG_SYMBOLS="${DEBUG_SYMBOLS:-}"
OUTPUT="${OUTPUT:-$repo/binary/dist}"
FRANKENPHP_SRC="${FRANKENPHP_SRC:-$repo/binary/.frankenphp}"
BUILD_PROXY="${BUILD_PROXY:-${HTTPS_PROXY:-${https_proxy:-}}}"

# shellcheck source=binary/build-manifest.sh
. "$(dirname "${BASH_SOURCE[0]}")/build-manifest.sh"

ALWAYS_COMPILED_IN="hash json pcre"

ALWAYS_IN_THE_BINARY="opcache pdo_mysql pdo_pgsql pdo_sqlite intl"

IMPOSSIBLE_IN_A_THREADED_BINARY="imap"

EXTENSION_LIBS="freetype,libjpeg,libwebp,libavif"

PLUGIN_DIR="yeswiki-binary"

CADDY_MODULES=(
    "--with github.com/dunglas/caddy-cbrotli"
    "--with github.com/dunglas/mercure/caddy"
    "--with github.com/dunglas/vulcain/caddy"
    "--with github.com/YesWiki/yeswiki/binary=/go/src/app/${PLUGIN_DIR}"
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

# Warns before the build when the anonymous GitHub API quota will not last it.
warn_about_the_github_rate_limit() {
    if [ -n "${GITHUB_TOKEN:-}" ]; then
        printf 'github api: authenticated\n'

        return
    fi

    local remaining
    remaining="$(curl -fsS --max-time 10 https://api.github.com/rate_limit 2>/dev/null \
        | php -r '$j = json_decode(stream_get_contents(STDIN), true); echo $j["resources"]["core"]["remaining"] ?? "";' 2>/dev/null)"

    if [ -z "$remaining" ]; then
        printf 'github api: could not be asked about its rate limit; carrying on\n'

        return
    fi

    printf 'github api: %s anonymous requests left this hour\n' "$remaining"
    if [ "$remaining" -lt 40 ]; then
        printf 'that is not enough for a build. Export GITHUB_TOKEN, or wait for the hour to roll over.\n' >&2
    fi
}

main() {
    local extensions
    extensions="$(php_extensions)"

    printf 'frankenphp %s, php %s, %s\n' "$FRANKENPHP_VERSION" "$PHP_VERSION" "$TARGETARCH"
    printf 'extensions: %s\n' "$extensions"
    printf 'extension libs: %s\n' "$EXTENSION_LIBS"
    printf 'downloads go through %s\n' "${BUILD_PROXY:-no proxy}"
    warn_about_the_github_rate_limit

    "$repo/binary/build-program.sh" >/dev/null
    write_build_manifest "$repo" "$FRANKENPHP_VERSION" "$PHP_VERSION" "$TARGETARCH" \
        "$extensions" "$EXTENSION_LIBS" "${CADDY_MODULES[*]}" "${COMPRESS:-0}" "static-musl" \
        > "$repo/binary/program/BUILD.json"

    if [ ! -d "$FRANKENPHP_SRC/.git" ]; then
        rm -rf "$FRANKENPHP_SRC"
        git clone --depth 1 --branch "v${FRANKENPHP_VERSION}" https://github.com/php/frankenphp.git "$FRANKENPHP_SRC"
    fi
    git -C "$FRANKENPHP_SRC" fetch --depth 1 origin "refs/tags/v${FRANKENPHP_VERSION}:refs/tags/v${FRANKENPHP_VERSION}" >/dev/null 2>&1 || true
    git -C "$FRANKENPHP_SRC" checkout --quiet "v${FRANKENPHP_VERSION}"

    local staged="$FRANKENPHP_SRC/$PLUGIN_DIR"
    rm -rf "$staged"
    mkdir -p "$staged"
    tar -C "$repo/binary" --exclude=./.frankenphp --exclude=./dist -cf - . | tar -C "$staged" -xf -
    printf 'staged the caddy plugin and the Program into %s\n' "$staged"

    local image="yeswiki-static-builder-${TARGETARCH}"
    local golang_base network=()
    golang_base="$(cd "$FRANKENPHP_SRC" && docker buildx bake --print static-builder-musl 2>/dev/null \
        | php -r '$j = json_decode(stream_get_contents(STDIN), true); echo $j["target"]["static-builder-musl"]["contexts"]["golang-base"];')"

    if [ -n "$BUILD_PROXY" ]; then
        network=(--network=host --allow=network.host
            --build-arg "HTTP_PROXY=${BUILD_PROXY}"
            --build-arg "HTTPS_PROXY=${BUILD_PROXY}"
            --build-arg "http_proxy=${BUILD_PROXY}"
            --build-arg "https_proxy=${BUILD_PROXY}"
            --build-arg "NO_PROXY=localhost,127.0.0.1")
    fi

    local attempts="${BUILD_ATTEMPTS:-3}" attempt
    for attempt in $(seq "$attempts"); do
        if ( cd "$FRANKENPHP_SRC" && GITHUB_TOKEN="${GITHUB_TOKEN:-}" docker buildx build --load \
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
            . ); then
            break
        fi
        if [ "$attempt" -ge "$attempts" ]; then
            printf 'the image did not build in %s attempts\n' "$attempts" >&2
            exit 1
        fi
        printf 'build attempt %s of %s failed; retrying from the last step that succeeded\n' "$attempt" "$attempts" >&2
        sleep 15
    done

    mkdir -p "$OUTPUT"
    local container
    container="$(docker create "$image")"
    docker cp "$container:/go/src/app/dist/frankenphp-linux-${TARGETARCH}" "$OUTPUT/yeswiki-linux-${TARGETARCH}"
    docker rm "$container" >/dev/null

    chmod +x "$OUTPUT/yeswiki-linux-${TARGETARCH}"

stamp_build_manifest "$repo/binary/program/BUILD.json" "$OUTPUT/yeswiki-linux-${TARGETARCH}" \
        > "$OUTPUT/yeswiki-linux-${TARGETARCH}.build.json"

    printf 'built %s\n' "$OUTPUT/yeswiki-linux-${TARGETARCH}"
    ls -lh "$OUTPUT/yeswiki-linux-${TARGETARCH}"
    cat "$OUTPUT/yeswiki-linux-${TARGETARCH}.build.json"
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
