#!/bin/bash
# Installs a fresh wiki for the next spec, on any driver and either runtime (fpm or the binary).
# Usage: YESWIKI_TEST_DRIVER=mysql|pgsql|sqlite YESWIKI_TEST_RUNTIME=fpm|binary bash tests/e2e/reset.sh

set -e

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
        | mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASSWORD}"
      ;;
    pgsql)
      export PGPASSWORD="${DB_PASSWORD}"
      psql -h "${DB_HOST}" -U "${DB_USER}" -d template1 -q \
        -c "DROP DATABASE IF EXISTS ${DB_NAME} WITH (FORCE);" \
        -c "CREATE DATABASE ${DB_NAME};"
      ;;
    sqlite)
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

    rm -f "${INSTANCE}/test.config.php" "${INSTANCE}/${YESWIKI_CONFIG_FILE:-yeswiki.config.php}"
    rm -rf "${INSTANCE}/cache/"* 2>/dev/null || true
    drop_and_create

    mapfile -t arguments < <(installer_arguments)
    php "${ROOT}/src/commands/console" core:install "${arguments[@]}"
    "${ROOT}/yeswicli" migrate
    if [ "${YESWIKI_TEST_ONBOARDED:-1}" = "1" ]; then
      "${ROOT}/yeswicli" onboarding:apply --all
    fi
    ;;

  binary)
    BINARY="${YESWIKI_TEST_BINARY:-${ROOT}/binary/dist/yeswiki-linux-$(uname -m)}"
    INSTANCE="${YESWIKI_TEST_INSTANCE:-/tmp/yeswiki-e2e}"
    BASE_URL="${YESWIKI_TEST_BASE_URL:-http://127.0.0.1:8081/?}"

    if [ ! -x "$BINARY" ]; then
      echo "no binary at ${BINARY}: build it with \`make binary\` or set YESWIKI_TEST_BINARY" >&2
      exit 1
    fi

    RUNTIME_SCRIPT=(env YESWIKI_TEST_RUNTIME=binary YESWIKI_TEST_INSTANCE="${INSTANCE}" YESWIKI_TEST_BINARY="${BINARY}" bash "$(dirname "${BASH_SOURCE[0]}")/runtime.sh")
    SERVING=0
    if "${RUNTIME_SCRIPT[@]}" running; then
      SERVING=1
      "${RUNTIME_SCRIPT[@]}" stop
    fi

    export YESWIKI_PROGRAM_ROOT="${YESWIKI_TEST_PROGRAM_ROOT:-${INSTANCE}-program}"
    rm -rf "${INSTANCE}" "${YESWIKI_PROGRAM_ROOT}"
    mkdir -p "${INSTANCE}/private"
    drop_and_create

    mapfile -t arguments < <(installer_arguments)
    "$BINARY" setup "${INSTANCE}" "${arguments[@]}"
    "$BINARY" migrate "${INSTANCE}"
    if [ "${YESWIKI_TEST_ONBOARDED:-1}" = "1" ]; then
      "$BINARY" onboarding:apply --all --instance "${INSTANCE}"
    fi
    if [ "$SERVING" = "1" ]; then
      "${RUNTIME_SCRIPT[@]}" start
    fi
    ;;

  *)
    echo "unknown YESWIKI_TEST_RUNTIME '${RUNTIME}' (expected fpm or binary)" >&2
    exit 1
    ;;
esac

echo "reset: driver=${DRIVER} runtime=${RUNTIME} instance=${INSTANCE} base=${BASE_URL}"
