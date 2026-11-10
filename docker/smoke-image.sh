#!/usr/bin/env bash
# Starts an image with MariaDB beside it, installs a wiki on its first start, and checks it serves as a non-root user on a read-only root.
set -euo pipefail

image="${1:?usage: docker/smoke-image.sh <image>}"
name="yw-smoke-$$"

cleanup() {
    if [ "${passed:-}" != 1 ]; then
        docker logs "$name-wiki" 2>&1 | tail -40 || true
    fi
    docker rm -f "$name-wiki" "$name-db" >/dev/null 2>&1 || true
    docker volume rm "$name-data" >/dev/null 2>&1 || true
    docker network rm "$name" >/dev/null 2>&1 || true
}
trap cleanup EXIT

fail() {
    echo "smoke: $*" >&2
    exit 1
}

docker network create "$name" >/dev/null
docker run -d --name "$name-db" --network "$name" \
    -e MARIADB_ROOT_PASSWORD=smoke-root -e MARIADB_DATABASE=yeswiki -e MARIADB_USER=yeswiki -e MARIADB_PASSWORD=smoke-db \
    --health-cmd "healthcheck.sh --connect --innodb_initialized" --health-interval 2s \
    mariadb:11.4 >/dev/null

for _ in $(seq 1 60); do
    [ "$(docker inspect -f '{{.State.Health.Status}}' "$name-db")" = healthy ] && break
    sleep 2
done
[ "$(docker inspect -f '{{.State.Health.Status}}' "$name-db")" = healthy ] || fail "MariaDB never became healthy"

docker run -d --name "$name-wiki" --network "$name" --read-only --tmpfs /tmp \
    -v "$name-data:/srv/wiki" -p 127.0.0.1::8080 \
    -e YESWIKI_INSTALL=1 -e DB_DRIVER=mysql -e DB_HOST="$name-db" -e DB_DATABASE=yeswiki -e DB_USER=yeswiki -e DB_PASSWORD=smoke-db \
    -e BASE_URL='http://localhost:8080/?' -e ADMIN_NAME=WikiAdmin -e ADMIN_EMAIL=admin@example.org -e ADMIN_PASSWORD=smoke-password \
    "$image" >/dev/null

port="$(docker port "$name-wiki" 8080/tcp | head -1 | sed 's/.*://')"
ready=''
for _ in $(seq 1 90); do
    if ready="$(curl -fsS --noproxy '*' "http://127.0.0.1:$port/readyz" 2>/dev/null)"; then
        break
    fi
    [ "$(docker inspect -f '{{.State.Running}}' "$name-wiki")" = true ] || fail "the wiki stopped before it was ready"
    sleep 2
done
[ -n "$ready" ] || fail "/readyz never answered 200"
echo "smoke: readyz $ready"

status="$(curl -s --noproxy '*' -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/?PagePrincipale")"
[ "$status" = 200 ] || fail "the home page answered $status"

[ "$(docker exec "$name-wiki" id -u)" = 10001 ] || fail "the wiki does not run as uid 10001"
if docker exec "$name-wiki" sh -c 'touch /opt/yeswiki/written 2>/dev/null'; then
    fail "the program root is writable"
fi

docker restart "$name-wiki" >/dev/null
port="$(docker port "$name-wiki" 8080/tcp | head -1 | sed 's/.*://')"
for _ in $(seq 1 60); do
    curl -fsS --noproxy '*' -o /dev/null "http://127.0.0.1:$port/readyz" 2>/dev/null && break
    sleep 2
done
curl -fsS --noproxy '*' -o /dev/null "http://127.0.0.1:$port/?PagePrincipale" || fail "the wiki did not come back after a restart"

passed=1
echo "smoke: $image installs, serves, restarts, runs as 10001 on a read-only root"
