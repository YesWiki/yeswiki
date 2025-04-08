#!/bin/bash
cd /var/www/html
source /home/yeswiki/.nvm/nvm.sh
nvm use 20
corepack enable
yarn install

export PLAYWRIGHT_BROWSERS_PATH=0
yarn run playwright install-deps
yarn run playwright install
yarn run playwright test --ui --ui-port=8083 --ui-host 0.0.0.0 &

/var/www/html/docker/entrypoint.sh