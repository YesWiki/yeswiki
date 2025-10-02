#!/bin/bash


# Start entrypoint and wait for server to be ready
# Then run tests playwright
# Used on CI to run tests

set -e

/opt/entrypoint.sh &
while ! echo . | curl --silent --fail localhost:8085 > /dev/null; do sleep 1; done # Wait for server initialized
while ! echo . | nc yeswiki-db 3306; do sleep 1; done # Wait for server initialized

export PLAYWRIGHT_BROWSERS_PATH=0
yarn run playwright install

./tests/e2e/tests.sh