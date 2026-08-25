#!/bin/bash

set -e

# A fresh wiki for the next spec.
#
# Driver-aware since PostgreSQL joined the compose stack: ADR-0015 commits the search index
# to all three dialects, and the only way "supported" and "tested" can mean the same thing is
# if the browser suite can be pointed at each of them.
#
#   bash tests/e2e/reset.sh                            # MySQL under php-fpm, the default
#   YESWIKI_TEST_DRIVER=pgsql bash tests/e2e/reset.sh
#
# Runtime-aware since the binary became the recommended deployment (single-binary 06). The same
# argument applies one layer up: recommending a deployment CI never runs is the mistake ADR-0015's
# amendment warns about, with a different subject.
#
#   YESWIKI_TEST_RUNTIME=binary bash tests/e2e/reset.sh
#
# `fpm` installs into the checkout and nginx serves it, which is what this always did. `binary`
# installs a *separate Instance* through the shipped executable's own `setup`, because a binary
# tested against the source tree is not the artefact anybody downloads.
#
# Hosts are overridable so the same script runs under docker compose, where the database is
# `yeswiki-db`, and on a CI runner, where it is a service container on 127.0.0.1.

DRIVER="${YESWIKI_TEST_DRIVER:-mysql}"
RUNTIME="${YESWIKI_TEST_RUNTIME:-fpm}"
ROOT="${YESWIKI_TEST_ROOT:-/var/www/html}"

case "$DRIVER" in
  mysql)
    DB_HOST="${YESWIKI_TEST_DB_HOST:-yeswiki-db}"
    DB_USER="${YESWIKI_TEST_DB_USER:-root}"
    DB_PASSWORD="${YESWIKI_TEST_DB_PASSWORD:-root}"
    DB_NAME="${YESWIKI_TEST_DB_NAME:-yeswiki_test}"
    ;;
  pgsql)
    DB_HOST="${YESWIKI_TEST_DB_HOST:-yeswiki-pg}"
    DB_USER="${YESWIKI_TEST_DB_USER:-yeswiki}"
    DB_PASSWORD="${YESWIKI_TEST_DB_PASSWORD:-password}"
    DB_NAME="${YESWIKI_TEST_DB_NAME:-yeswiki_test}"
    ;;
  sqlite)
    DB_HOST=""
    DB_USER=""
    DB_PASSWORD=""
    DB_NAME=""
    ;;
  *)
    echo "unknown YESWIKI_TEST_DRIVER '${DRIVER}' (expected mysql, pgsql or sqlite)" >&2
    exit 1
    ;;
esac

drop_and_create() {
  case "$DRIVER" in
    mysql)
      echo "DROP DATABASE IF EXISTS ${DB_NAME}; CREATE DATABASE ${DB_NAME};" \
        | mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASSWORD}" --skip-ssl
      ;;
    pgsql)
      export PGPASSWORD="${DB_PASSWORD}"
      # Connected to `template1`, not to the database being dropped -- psql has to be attached
      # to something else to drop it. WITH (FORCE) terminates whatever connection php-fpm is
      # still holding; without it a reset between specs fails with "database is being accessed
      # by other users", which MySQL never does.
      psql -h "${DB_HOST}" -U "${DB_USER}" -d template1 -q \
        -c "DROP DATABASE IF EXISTS ${DB_NAME} WITH (FORCE);" \
        -c "CREATE DATABASE ${DB_NAME};"
      ;;
    sqlite)
      # The installer puts the file at a fixed private/yeswiki.db, and a stale one is a wiki
      # that is already installed.
      rm -f "${INSTANCE}/private/yeswiki.db"
      ;;
  esac
}

installer_arguments() {
  printf '%s\n' \
    "--no-interaction" \
    "--driver=${DRIVER}" \
    "--table-prefix=yeswiki_" \
    "--base-url=${BASE_URL}" \
    "--root-page=PagePrincipale" \
    "--wiki-name=MyTestWiki" \
    "--language=fr" \
    "--other-languages=en,es" \
    "--allow-raw-html" \
    "--admin-name=WikiAdmin" \
    "--admin-email=test@example.com" \
    "--admin-password=WikiAdminPassword"

  if [ "$DRIVER" != "sqlite" ]; then
    printf '%s\n' \
      "--db-host=${DB_HOST}" \
      "--db-database=${DB_NAME}" \
      "--db-user=${DB_USER}" \
      "--db-password=${DB_PASSWORD}"
  fi
}

case "$RUNTIME" in
  fpm)
    INSTANCE="$ROOT"
    BASE_URL="${YESWIKI_TEST_BASE_URL:-http://yeswiki-web/?}"

    rm -f "${INSTANCE}/test.config.php"
    drop_and_create

    mapfile -t arguments < <(installer_arguments)
    php "${ROOT}/src/commands/console" core:install "${arguments[@]}"
    "${ROOT}/yeswicli" migrate
    ;;

  binary)
    BINARY="${YESWIKI_TEST_BINARY:-${ROOT}/binary/dist/yeswiki-linux-$(uname -m)}"
    INSTANCE="${YESWIKI_TEST_INSTANCE:-/tmp/yeswiki-e2e}"
    BASE_URL="${YESWIKI_TEST_BASE_URL:-http://127.0.0.1:8081/?}"

    if [ ! -x "$BINARY" ]; then
      echo "no binary at ${BINARY}: build it with \`make binary\` or set YESWIKI_TEST_BINARY" >&2
      exit 1
    fi

    # The Program root goes with the Instance, so a reset leaves nothing of the last run behind
    # and `setup` writes the Program fresh out of the executable being tested.
    export YESWIKI_PROGRAM_ROOT="${YESWIKI_TEST_PROGRAM_ROOT:-${INSTANCE}-program}"
    rm -rf "${INSTANCE}" "${YESWIKI_PROGRAM_ROOT}"
    mkdir -p "${INSTANCE}/private"
    drop_and_create

    mapfile -t arguments < <(installer_arguments)
    "$BINARY" setup "${INSTANCE}" "${arguments[@]}"
    "$BINARY" migrate "${INSTANCE}"
    ;;

  *)
    echo "unknown YESWIKI_TEST_RUNTIME '${RUNTIME}' (expected fpm or binary)" >&2
    exit 1
    ;;
esac

echo "reset: driver=${DRIVER} runtime=${RUNTIME} instance=${INSTANCE} base=${BASE_URL}"
