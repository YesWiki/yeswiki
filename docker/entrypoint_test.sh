#!/bin/bash
# Starts the test container: the Playwright UI on port 8083 beside the dev entrypoint.
set -euo pipefail
cd /var/www/html

if [ ! -f node_modules/.yarn-integrity ] || [ yarn.lock -nt node_modules/.yarn-integrity ]; then
    yarn install
fi

export PLAYWRIGHT_BROWSERS_PATH=0
yarn run playwright install
yarn run playwright test --ui --ui-port=8083 --ui-host 0.0.0.0 &

exec /var/www/html/docker/entrypoint.sh
