#!/bin/bash

set -e

# Start the runtime the browser suite is about to be pointed at, and wait until it answers.
#
# `fpm` is already running -- nginx and php-fpm are the compose stack, or the built-in server is
# the CI one -- so this only waits. `binary` starts the shipped executable in worker mode, which
# is the deployment single-binary 06 exists to stop recommending untested.
#
#   bash tests/e2e/runtime.sh start
#   bash tests/e2e/runtime.sh stop

RUNTIME="${YESWIKI_TEST_RUNTIME:-fpm}"
ROOT="${YESWIKI_TEST_ROOT:-/var/www/html}"
INSTANCE="${YESWIKI_TEST_INSTANCE:-/tmp/yeswiki-e2e}"
BINARY="${YESWIKI_TEST_BINARY:-${ROOT}/binary/dist/yeswiki-linux-$(uname -m)}"
ADDRESS="${YESWIKI_TEST_ADDRESS:-127.0.0.1:8081}"
PIDFILE="${YESWIKI_TEST_PIDFILE:-/tmp/yeswiki-e2e-serve.pid}"
LOGFILE="${YESWIKI_TEST_LOGFILE:-/tmp/yeswiki-e2e-serve.log}"

# One worker thread, deliberately. Several would let a request that leaks state be answered by a
# thread that never saw the leak, so a repeated-request test would pass by luck.
WORKERS="${YESWIKI_TEST_WORKERS:-1}"

url() {
  case "$RUNTIME" in
    binary) printf 'http://%s/?PagePrincipale' "$ADDRESS" ;;
    *) printf '%s' "${YESWIKI_TEST_URL:-http://yeswiki-web/?PagePrincipale}" ;;
  esac
}

wait_for_it() {
  local address
  address="$(url)"
  for _ in $(seq 60); do
    if curl --silent --fail --max-time 5 "$address" > /dev/null 2>&1; then
      echo "runtime ${RUNTIME} answers at ${address}"
      return 0
    fi
    sleep 1
  done

  echo "runtime ${RUNTIME} did not answer at ${address}" >&2
  [ -f "$LOGFILE" ] && tail -50 "$LOGFILE" >&2
  return 1
}

start() {
  if [ "$RUNTIME" != "binary" ]; then
    wait_for_it
    return
  fi

  export YESWIKI_PROGRAM_ROOT="${YESWIKI_TEST_PROGRAM_ROOT:-${INSTANCE}-program}"
  # `serve` restarts a worker after this many requests, and the repeated-request specs issue more
  # than a handful. A restart mid-suite would hide exactly the leak they are looking for.
  export YESWIKI_WORKER_REQUESTS="${YESWIKI_WORKER_REQUESTS:-100000}"

  nohup "$BINARY" serve "$INSTANCE" --listen "$ADDRESS" --workers "$WORKERS" > "$LOGFILE" 2>&1 &
  echo $! > "$PIDFILE"

  wait_for_it
}

stop() {
  if [ -f "$PIDFILE" ]; then
    kill "$(cat "$PIDFILE")" 2>/dev/null || true
    rm -f "$PIDFILE"
  fi
}

case "${1:-start}" in
  start) start ;;
  stop) stop ;;
  url) url ;;
  *) echo "usage: runtime.sh [start|stop|url]" >&2; exit 1 ;;
esac
