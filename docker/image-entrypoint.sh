#!/bin/sh
# Starts the wiki in the image: puts back its entry points, installs it on a first start when YESWIKI_INSTALL=1, migrates it unless it is replicated, then serves it.
set -eu

wiki=/srv/wiki

installed() {
    grep -q "'base_url'" "$wiki/yeswiki.config.php" 2>/dev/null
}

case "${1:-serve}" in
    serve)
        yeswiki prepare "$wiki"
        if ! installed; then
            if [ "${YESWIKI_INSTALL:-}" != 1 ]; then
                echo "no wiki in $wiki yet: set YESWIKI_INSTALL=1 with DB_*, BASE_URL and ADMIN_* to install one" >&2
                exit 1
            fi
            yeswiki setup "$wiki" --no-interaction
        fi
        if [ "${YESWIKI_REPLICATED:-}" != 1 ]; then
            yeswiki migrate "$wiki"
        fi
        exec yeswiki serve "$wiki" --listen ":${PORT:-8080}"
        ;;
    migrate)
        yeswiki prepare "$wiki"
        exec yeswiki migrate "$wiki"
        ;;
    *)
        exec yeswiki "$@"
        ;;
esac
