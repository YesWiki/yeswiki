#!/bin/sh
# Starts the wiki in the image: puts back its entry points, migrates it unless it is replicated (a database with no schema gets one), then serves it.
# `install` and `migrate` are what a cluster's Jobs run; `bundle <names>` adds extensions and themes to the program, as root in an image built FROM this one.
set -eu

wiki=/srv/wiki

configured() {
    [ -n "${DB_DATABASE:-}" ] || [ "${DB_DRIVER:-}" = sqlite ] \
        || grep -qE '^(export )?DB_(DATABASE|DRIVER)=' "$wiki/private/.env" 2>/dev/null \
        || grep -q "'base_url'" "$wiki/yeswiki.config.php" 2>/dev/null
}

case "${1:-serve}" in
    serve)
        yeswiki prepare "$wiki"
        if ! configured; then
            echo "no wiki in $wiki: set DB_* and BASE_URL (and ADMIN_* for a first account), or put them in private/.env" >&2
            exit 1
        fi
        if [ "${YESWIKI_REPLICATED:-}" != 1 ]; then
            yeswiki migrate "$wiki"
        fi
        set -- serve "$wiki" --listen ":${PORT:-8080}"
        if [ -n "${YESWIKI_WORKERS:-}" ]; then
            set -- "$@" --workers "$YESWIKI_WORKERS"
        fi
        exec yeswiki "$@"
        ;;
    migrate)
        yeswiki prepare "$wiki"
        exec yeswiki migrate "$wiki"
        ;;
    install)
        yeswiki prepare "$wiki"
        yeswiki setup "$wiki" --no-interaction
        exec yeswiki migrate "$wiki"
        ;;
    bundle)
        shift
        program="$(echo /opt/yeswiki/program-*)"
        cd "$program"
        YESWIKI_PROGRAM_DIR="$program" YESWIKI_INSTANCE_DIR="$program" exec yeswiki php-cli src/commands/console core:bundle "$@"
        ;;
    *)
        exec yeswiki "$@"
        ;;
esac
