#!/usr/bin/env bash
# Runs the core suite then every tools/*/tests folder, and fails if any of them failed.
# Extra arguments go to each phpunit run, e.g. `composer test -- --filter BotGuard`.

cd "$(dirname "$0")/.." || exit 1

status=0
for suite in tests tools/*/tests; do
    [ -d "$suite" ] || continue
    echo "== $suite"
    ./vendor/bin/phpunit --do-not-cache-result --display-warnings --display-deprecations --stderr "$suite" "$@" || status=1
done

exit $status
