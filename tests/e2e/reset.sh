#!/bin/bash
# Installs a fresh wiki for the next spec, on any driver and any runtime: fpm, the binary, or the replicas deploy/kind/run.sh puts on a cluster.
# Usage: YESWIKI_TEST_DRIVER=mysql|pgsql|sqlite YESWIKI_TEST_RUNTIME=fpm|binary|cluster bash tests/e2e/reset.sh

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
    "--ci-api" \
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

seed_program() {
  local version dir
  version="$("$BINARY" version --build | php -r 'echo json_decode(stream_get_contents(STDIN), true)["version"];')"
  dir="${YESWIKI_PROGRAM_ROOT}/program-${version}"
  if [ "$(cat "${dir}/.yeswiki-program" 2>/dev/null)" = "$version" ]; then
    return
  fi
  rm -rf "$dir"
  mkdir -p "$YESWIKI_PROGRAM_ROOT"
  cp -a "${YESWIKI_TEST_PROGRAM}/." "$dir"
  printf '%s\n' "$version" > "${dir}/.yeswiki-program"
  echo "program: ${YESWIKI_TEST_PROGRAM} stands in for the one the binary carries (${version})"
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
    # What the install queued -- the search index among it -- is done before a spec looks.
    "${ROOT}/yeswicli" messenger:consume --drain
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
    elif "${RUNTIME_SCRIPT[@]}" crashed; then
      SERVING=1
      "${RUNTIME_SCRIPT[@]}" stop
    fi

    export YESWIKI_PROGRAM_ROOT="${YESWIKI_TEST_PROGRAM_ROOT:-${INSTANCE}-program}"
    rm -rf "${INSTANCE}"
    if [ -n "${YESWIKI_TEST_PROGRAM:-}" ]; then
      seed_program
    else
      rm -rf "${YESWIKI_PROGRAM_ROOT}"
    fi
    mkdir -p "${INSTANCE}/private"
    drop_and_create

    mapfile -t arguments < <(installer_arguments)
    "$BINARY" setup "${INSTANCE}" "${arguments[@]}"
    "$BINARY" migrate "${INSTANCE}"
    if [ "${YESWIKI_TEST_ONBOARDED:-1}" = "1" ]; then
      "$BINARY" onboarding:apply --all --instance "${INSTANCE}"
    fi
    "$BINARY" messenger:consume --drain --instance "${INSTANCE}"
    if [ "$SERVING" = "1" ]; then
      "${RUNTIME_SCRIPT[@]}" start
    fi
    ;;

  cluster)
    RELEASE="${YESWIKI_TEST_RELEASE:-wiki}"
    REPLICAS=(-l "app.kubernetes.io/instance=${RELEASE},app.kubernetes.io/component!=install")
    INSTANCE=/tmp/reset
    BASE_URL="${YESWIKI_TEST_BASE_URL:?the address the browser reaches the replicas at}"
    DB_HOST=db
    DB_USER=yeswiki
    DB_PASSWORD=kind-db
    DB_NAME=yeswiki

    pod=""
    for _ in $(seq 90); do
      pod="$(kubectl get pods "${REPLICAS[@]}" -o jsonpath='{range .items[*]}{.metadata.deletionTimestamp}{.status.containerStatuses[0].ready} {.metadata.name}{"\n"}{end}' | awk '$1 == "true" { print $2; exit }')"
      [ -n "$pod" ] && break
      sleep 1
    done
    [ -n "$pod" ] || { echo "no ready replica to install from" >&2; exit 1; }

    case "$DRIVER" in
      mysql) kubectl exec deploy/db -- mariadb -uroot -pkind-root -e "DROP DATABASE IF EXISTS ${DB_NAME}; CREATE DATABASE ${DB_NAME};" ;;
      pgsql) kubectl exec deploy/db -- psql -q -U yeswiki -d template1 -c "DROP DATABASE IF EXISTS ${DB_NAME} WITH (FORCE);" -c "CREATE DATABASE ${DB_NAME};" ;;
      *) echo "replicas need mysql or pgsql, not ${DRIVER}" >&2; exit 1 ;;
    esac
    kubectl exec deploy/s3 -- sh -c 'echo "s3.bucket.delete -name yeswiki" | weed shell >/dev/null; echo "s3.bucket.create -name yeswiki" | weed shell >/dev/null'

    mapfile -t arguments < <(installer_arguments)
    # shellcheck disable=SC2016
    kubectl exec "$pod" -- env ONBOARDED="${YESWIKI_TEST_ONBOARDED:-1}" sh -c '
      set -e
      rm -rf "$0" && mkdir -p "$0"
      yeswiki prepare "$0" >/dev/null
      yeswiki setup "$0" "$@" >/dev/null
      yeswiki migrate "$0" >/dev/null
      if [ "$ONBOARDED" = 1 ]; then yeswiki onboarding:apply --all --instance "$0" >/dev/null; fi
      yeswiki messenger:consume --drain --instance "$0" >/dev/null
      rm -rf "$0"
    ' "$INSTANCE" "${arguments[@]}"

    kubectl delete pods "${REPLICAS[@]}" --grace-period=1 --wait=true --timeout=120s >/dev/null
    wanted="$(kubectl get "deployment/${RELEASE}-yeswiki" -o jsonpath='{.spec.replicas}')"
    for _ in $(seq 180); do
      ready="$(kubectl get pods "${REPLICAS[@]}" -o jsonpath='{range .items[*]}{.metadata.deletionTimestamp}{.status.containerStatuses[0].ready}{"\n"}{end}' | grep -c '^true$' || true)"
      [ "$ready" = "$wanted" ] && break
      sleep 1
    done
    [ "$ready" = "$wanted" ] || { echo "only ${ready} of ${wanted} replicas came back ready" >&2; exit 1; }
    for _ in $(seq 30); do
      answers="$(for _ in 1 2 3 4 5 6; do curl -s --max-time 5 --noproxy '*' -H 'Connection: close' -o /dev/null -w '%{http_code}\n' "${BASE_URL}PagePrincipale"; done | sort -u)"
      [ "$answers" = 200 ] && break
      sleep 1
    done
    ;;

  *)
    echo "unknown YESWIKI_TEST_RUNTIME '${RUNTIME}' (expected fpm, binary or cluster)" >&2
    exit 1
    ;;
esac

echo "reset: driver=${DRIVER} runtime=${RUNTIME} instance=${INSTANCE} base=${BASE_URL}"
