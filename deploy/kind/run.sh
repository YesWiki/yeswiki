#!/usr/bin/env bash
# Proves the chart on a kind cluster: a database (mariadb or postgresql) and SeaweedFS in it, the image loaded, three replicas installed and answering, then an upgrade.
# Usage: deploy/kind/run.sh <mariadb|postgresql> <image> [helm --set ...]; KEEP=1 leaves the cluster up.
set -euo pipefail

driver="${1:?usage: deploy/kind/run.sh <mariadb|postgresql> <image>}"
image="${2:?usage: deploy/kind/run.sh <mariadb|postgresql> <image>}"
shift 2
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
chart="$here/../helm/yeswiki"
cluster="yeswiki-$driver"
release=wiki
deployment="$release-yeswiki"
curl_image=curlimages/curl:8.11.1

case "$driver" in
    mariadb) db_image=mariadb:11.4 db_driver=mysql db_port=3306 ;;
    postgresql) db_image=postgres:16-alpine db_driver=pgsql db_port=5432 ;;
    *) echo "no database called $driver" >&2; exit 1 ;;
esac

say() {
    printf '\n== %s\n' "$*"
}

cleanup() {
    status=$?
    if [ "$status" != 0 ]; then
        kubectl get pods -o wide 2>/dev/null || true
        for pod in $(kubectl get pods -o name 2>/dev/null); do
            echo "--- $pod"
            kubectl logs "$pod" --all-containers --tail=30 2>/dev/null || true
        done
    fi
    if [ "${KEEP:-}" != 1 ]; then
        kind delete cluster --name "$cluster" >/dev/null 2>&1 || true
    fi
    exit "$status"
}
trap cleanup EXIT

say "a kind cluster with $driver and SeaweedFS"
kind delete cluster --name "$cluster" >/dev/null 2>&1 || true
kind create cluster --name "$cluster" --wait 120s
for needed in "$db_image" chrislusf/seaweedfs:4.53 "$curl_image"; do
    docker image inspect "$needed" >/dev/null 2>&1 || docker pull -q "$needed" >/dev/null
done
platform="linux/$(docker info --format '{{.Architecture}}' | sed 's/x86_64/amd64/; s/aarch64/arm64/')"
archive="$(mktemp)"
for loaded in "$image" "$db_image" chrislusf/seaweedfs:4.53 "$curl_image"; do
    docker save --platform "$platform" -o "$archive" "$loaded" 2>/dev/null || docker save -o "$archive" "$loaded"
    kind load image-archive --name "$cluster" "$archive"
done
rm -f "$archive"
kubectl apply -f "$here/$driver.yaml" -f "$here/seaweedfs.yaml"
kubectl wait --for=condition=available deployment/db deployment/s3 --timeout=180s

values=(
    --set "image.repository=${image%:*}" --set "image.tag=${image##*:}" --set image.pullPolicy=Never
    --set "baseUrl=http://$deployment/?"
    --set "database.driver=$db_driver" --set database.host=db --set "database.port=$db_port" --set database.password=kind-db
    --set storage.bucket=yeswiki --set storage.endpoint=http://s3:8333 --set storage.publicUrl=http://s3:8333/yeswiki
    --set storage.pathStyle=true --set storage.key=kind-key --set storage.secret=kind-secret
    --set install.adminEmail=admin@example.org --set install.adminPassword=kind-admin-password
    "$@"
)

say "helm install: three replicas, the install Job"
helm install "$release" "$chart" "${values[@]}" --wait --timeout 10m
kubectl rollout status "deployment/$deployment" --timeout=300s

ask() {
    kubectl run "probe-$RANDOM" --rm -i --restart=Never --quiet --image="$curl_image" --image-pull-policy=Never -- \
        sh -c "$1"
}

say "every replica answers, through the Service"
answers="$(ask "for i in \$(seq 1 30); do curl -s -o /dev/null -w '%{http_code}\n' 'http://$deployment/?PagePrincipale'; done | sort | uniq -c")"
echo "$answers"
if grep -qv ' 200$' <<<"$answers"; then
    echo 'a request through the Service was not answered 200' >&2
    exit 1
fi
for pod in $(kubectl get pods -l app.kubernetes.io/instance="$release",app.kubernetes.io/component!=install -o name); do
    ready="$(kubectl get "$pod" -o jsonpath='{.status.containerStatuses[0].ready}')"
    [ "$ready" = true ] || { echo "$pod is not ready" >&2; exit 1; }
done
ask "curl -s 'http://$deployment/readyz'"
echo

say "helm upgrade: the migrate Job, then a rolling restart"
helm upgrade "$release" "$chart" "${values[@]}" --set-string "podAnnotations.restartedAt=$(date +%s)" --wait --timeout 10m
kubectl rollout status "deployment/$deployment" --timeout=300s
ask "for i in \$(seq 1 10); do curl -s -o /dev/null -w '%{http_code}\n' 'http://$deployment/?PagePrincipale'; done | sort | uniq -c"

say "the chart serves a wiki on three replicas of $image with $driver"
