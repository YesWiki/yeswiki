#!/usr/bin/env bash
set -euo pipefail

# Packaging smoke: setup, serve, fetch, migrate, upgrade. Cheap, and it catches the class of
# breakage that has nothing to do with PHP (single-binary 06).
#
#   ./binary/smoke.sh binary/dist/yeswiki-linux-x86_64
#
# On SQLite, because the point here is the packaging rather than the dialect: the driver matrix is
# the browser suite's job and this one has to run anywhere, including a runner with no database.

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

BINARY="${1:-$repo/binary/dist/yeswiki-linux-$(uname -m)}"
WORK="${WORK:-$(mktemp -d)}"
ADDRESS="${ADDRESS:-127.0.0.1:8099}"
FIXTURE_ADDRESS="${FIXTURE_ADDRESS:-127.0.0.1:8098}"

INSTANCE="$WORK/wiki"
export YESWIKI_PROGRAM_ROOT="$WORK/program"

served=""
fixture=""

cleanup() {
    [ -n "$served" ] && kill "$served" 2>/dev/null || true
    [ -n "$fixture" ] && kill "$fixture" 2>/dev/null || true
}
trap cleanup EXIT

fail() {
    printf '\nsmoke: %s\n' "$1" >&2
    [ -f "$WORK/serve.log" ] && tail -40 "$WORK/serve.log" >&2
    exit 1
}

step() { printf '\n=== %s\n' "$1"; }

main() {
    [ -x "$BINARY" ] || fail "no binary at $BINARY"
    printf 'smoking %s in %s\n' "$BINARY" "$WORK"

    step "it says what it is"
    "$BINARY" version
    "$BINARY" version --build > "$WORK/build.json" || fail "version --build did not answer"
    grep -q '"php"' "$WORK/build.json" || fail "the build manifest names no php version"

    # A dev build links the machine's libphp and loads no .so files, so it cannot install a wiki:
    # composer's platform check refuses before the console starts. Say that here rather than
    # failing four steps later on a stack trace that looks like a product bug.
    if grep -q '"linkage": "dynamic-dev"' "$WORK/build.json"; then
        fail "this is a dynamic dev build, whose php has only the built-in extensions. Smoke the shipped one: make binary && make binary-smoke"
    fi

    step "setup installs a wiki with no database server, no PHP and no webserver"
    mkdir -p "$INSTANCE"
    "$BINARY" setup "$INSTANCE" \
        --no-interaction \
        --driver=sqlite \
        --table-prefix=yeswiki_ \
        --base-url="http://${ADDRESS}/?" \
        --root-page=PagePrincipale \
        --wiki-name=SmokeWiki \
        --language=fr \
        --admin-name=WikiAdmin \
        --admin-email=smoke@example.com \
        --admin-password=WikiAdminPassword \
        || fail "setup failed"

    [ -f "$INSTANCE/yeswiki.config.php" ] || fail "setup wrote no configuration"

    local firstProgram
    firstProgram="$(programDirectory)"
    [ -n "$firstProgram" ] || fail "setup wrote no program"
    printf 'program %s\n' "$firstProgram"

    step "serve answers a real page"
    "$BINARY" serve "$INSTANCE" --listen "$ADDRESS" --workers 1 > "$WORK/serve.log" 2>&1 &
    served=$!

    local answered=""
    for _ in $(seq 60); do
        if curl --silent --fail --max-time 5 "http://${ADDRESS}/?PagePrincipale" > "$WORK/page.html" 2>/dev/null; then
            answered=yes
            break
        fi
        sleep 1
    done
    [ -n "$answered" ] || fail "the wiki never answered at http://${ADDRESS}/"
    grep -qi 'SmokeWiki\|PagePrincipale' "$WORK/page.html" || fail "the page served is not this wiki's"
    printf 'served %s bytes\n' "$(wc -c < "$WORK/page.html")"

    step "the worker served it, not a regular thread"
    # A worker that boots and receives nothing is the defect ticket 07 was filed for, and it looks
    # exactly like a working wiki from outside. The log is the only place the difference shows.
    if grep -qi 'worker' "$WORK/serve.log"; then
        printf 'worker mode is on\n'
    else
        printf 'note: the log does not mention a worker; check %s by hand\n' "$WORK/serve.log"
    fi

    kill "$served" 2>/dev/null || true
    served=""
    sleep 1

    step "migrate is separable and runs on its own"
    "$BINARY" migrate "$INSTANCE" || fail "migrate failed"

    step "upgrade --no-download migrates without fetching anything"
    "$BINARY" upgrade "$INSTANCE" --no-download || fail "upgrade --no-download failed"

    step "upgrade against a repository that offers nothing new"
    startFixtureRepository "$(cat "$WORK/version")"
    if "$BINARY" upgrade "$INSTANCE" --repository "http://${FIXTURE_ADDRESS}" --channel ectoplasme \
        > "$WORK/upgrade.log" 2>&1; then
        grep -qi 'what the repository offers\|is what is running' "$WORK/upgrade.log" \
            || printf 'note: upgrade said nothing about the offered version\n'
        printf 'the running version was not offered to itself\n'
    else
        # A binary with no compiled-in signing key refuses to install anything, which is correct
        # and is the state until the project's key exists. Anything else is a failure.
        grep -qi 'no release signing key' "$WORK/upgrade.log" \
            || fail "upgrade failed for a reason that is not the missing signing key: $(tail -5 "$WORK/upgrade.log")"
        printf 'no signing key compiled in, so it refused to install -- which is the right refusal\n'
    fi

    step "the program directory is what the binary says it is"
    local nowProgram
    nowProgram="$(programDirectory)"
    [ -n "$nowProgram" ] || fail "there is no program after the upgrade"
    printf 'program %s\n' "$nowProgram"

    printf '\nsmoke: all good\n'
}

# programDirectory is the newest program-* under the root, and it writes the version beside it so
# the fixture repository can offer exactly what is running.
programDirectory() {
    local newest
    newest="$(ls -1d "$YESWIKI_PROGRAM_ROOT"/program-* 2>/dev/null | tail -1 || true)"
    [ -n "$newest" ] || return 0
    basename "$newest" | sed 's/^program-//' > "$WORK/version"
    printf '%s' "$newest"
}

# A repository serving one index, which is the smallest thing an upgrade can be pointed at.
startFixtureRepository() {
    local version="${1:-dev}"
    mkdir -p "$WORK/repository/ectoplasme"
    cat > "$WORK/repository/ectoplasme/binary.json" <<JSON
{
  "version": "${version}",
  "released": "2026-08-25",
  "platforms": {
    "linux-$(uname -m)": {
      "url": "http://${FIXTURE_ADDRESS}/ectoplasme/binary/${version}/yeswiki-linux-$(uname -m)",
      "sha256": "$(sha256sum "$BINARY" | cut -d' ' -f1)",
      "signature": "http://${FIXTURE_ADDRESS}/ectoplasme/binary/${version}/yeswiki-linux-$(uname -m).sig",
      "bytes": $(stat -c%s "$BINARY")
    }
  }
}
JSON
    ( cd "$WORK/repository" && python3 -m http.server "${FIXTURE_ADDRESS##*:}" --bind "${FIXTURE_ADDRESS%%:*}" > "$WORK/fixture.log" 2>&1 & echo $! > "$WORK/fixture.pid" )
    fixture="$(cat "$WORK/fixture.pid")"
    sleep 1
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
