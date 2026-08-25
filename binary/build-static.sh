#!/usr/bin/env bash
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

FRANKENPHP_VERSION="${FRANKENPHP_VERSION:-1.12.7}"
# Pinned to a patch, not a minor. `spc download --with-php=8.4` resolves to whatever 8.4.x is
# current on the day, so a minor here means two builds of the same tag ship two interpreters.
PHP_VERSION="${PHP_VERSION:-8.4.24}"
TARGETARCH="${TARGETARCH:-$(uname -m)}"
# Upstream tests `[ -n "$COMPRESS" ]`, so **any** non-empty value means yes -- `COMPRESS=0` packs
# the binary with UPX, which is the opposite of what it reads like and costs ten minutes. Normalise
# here so this script's own contract is the obvious one: 1 compresses, anything falsy does not.
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
# Two constraints, and getting either wrong fails 16 minutes in, at the xcaddy link.
#
# The path has to be a container path: the replacement is resolved inside the build, not here. The
# Dockerfile's `COPY --link . ./` puts the context at /go/src/app, so staging the module at
# <context>/$PLUGIN_DIR makes it /go/src/app/$PLUGIN_DIR there.
#
# And what `--with` names has to be a *module* whose root holds the plugin package, because xcaddy
# turns it into both a `require` and an `import`. That is why this package sits at the module root
# rather than in a caddy/ subdirectory: `github.com/YesWiki/yeswiki/binary/caddy` was a package
# inside module `github.com/YesWiki/yeswiki/binary`, and go resolved it to a module rooted at a
# directory with no .go files in it. Splitting caddy/ off into its own module does not work either
# -- it would depend on the parent module, and a `replace` in a dependency is ignored, so xcaddy
# would go to the network for a module that is not published.
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

# static-php-cli asks the GitHub API for the current version of most of its dependencies, and
# anonymous callers get 60 requests an hour per address. A build uses most of them, so the second
# build of an afternoon fails part-way with a wall of `curl: (22) ... 403` and then a type error
# out of Downloader::downloadFile -- which reads like a broken toolchain rather than a quota.
#
# CI passes a token (`release.yml`, `e2e.yml`) and never sees this. A laptop does. Say it before
# the build starts rather than twenty minutes in.
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

    # Excluding this checkout, which lives under the module being copied, and dist/.
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

    # The same manifest beside the artefact, plus what only exists once it is built. This is what
    # the repository index serves and what a field report can be checked against.
stamp_build_manifest "$repo/binary/program/BUILD.json" "$OUTPUT/yeswiki-linux-${TARGETARCH}" \
        > "$OUTPUT/yeswiki-linux-${TARGETARCH}.build.json"

    printf 'built %s\n' "$OUTPUT/yeswiki-linux-${TARGETARCH}"
    ls -lh "$OUTPUT/yeswiki-linux-${TARGETARCH}"
    cat "$OUTPUT/yeswiki-linux-${TARGETARCH}.build.json"
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
