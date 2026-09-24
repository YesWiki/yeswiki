#!/bin/bash
# Starts, stops or checks the runtime the browser suite is pointed at: fpm is already up, binary is started here.
# Usage: bash tests/e2e/runtime.sh start|stop|running|url

set -e

RUNTIME="${YESWIKI_TEST_RUNTIME:-fpm}"
ROOT="${YESWIKI_TEST_ROOT:-/var/www/html}"
INSTANCE="${YESWIKI_TEST_INSTANCE:-/tmp/yeswiki-e2e}"
BINARY="${YESWIKI_TEST_BINARY:-${ROOT}/binary/dist/yeswiki-linux-$(uname -m)}"
ADDRESS="${YESWIKI_TEST_ADDRESS:-127.0.0.1:8081}"
PIDFILE="${YESWIKI_TEST_PIDFILE:-/tmp/yeswiki-e2e-serve.pid}"
LOGFILE="${YESWIKI_TEST_LOGFILE:-/tmp/yeswiki-e2e-serve.log}"
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

running() {
  [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null
}

start() {
  if [ "$RUNTIME" != "binary" ]; then
    wait_for_it
    return
  fi

  export YESWIKI_PROGRAM_ROOT="${YESWIKI_TEST_PROGRAM_ROOT:-${INSTANCE}-program}"
  export YESWIKI_WORKER_REQUESTS="${YESWIKI_WORKER_REQUESTS:-100000}"

  nohup "$BINARY" serve "$INSTANCE" --listen "$ADDRESS" --workers "$WORKERS" >> "$LOGFILE" 2>&1 &
  echo $! > "$PIDFILE"

  wait_for_it
}

stop() {
  if [ ! -f "$PIDFILE" ]; then
    return
  fi

  local pid
  pid="$(cat "$PIDFILE")"
  kill "$pid" 2>/dev/null || true
  for _ in $(seq 100); do
    kill -0 "$pid" 2>/dev/null || break
    sleep 0.1
  done
  kill -9 "$pid" 2>/dev/null || true
  rm -f "$PIDFILE"
}

case "${1:-start}" in
  start) start ;;
  stop) stop ;;
  running) running ;;
  url) url ;;
  *) echo "usage: runtime.sh [start|stop|running|url]" >&2; exit 1 ;;
esac
