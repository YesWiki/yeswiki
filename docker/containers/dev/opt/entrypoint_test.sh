#!/bin/bash

# Base entrypoint for test launch. Add playwright run

set -e

/opt/entrypoint.sh &

export PLAYWRIGHT_BROWSERS_PATH=0
yarn run playwright install
yarn run playwright test --ui --ui-port=8083 --ui-host 0.0.0.0