#!/bin/bash
# Starts the dev container: installs dependencies only when their lock file changed, migrates an installed wiki, then runs php-fpm.
set -euo pipefail
cd /var/www/html

if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/composer/installed.json ]; then
    composer install
fi
if [ ! -f node_modules/.yarn-integrity ] || [ yarn.lock -nt node_modules/.yarn-integrity ]; then
    yarn install
fi
if [ -f yeswiki.config.php ]; then
    ./yeswicli migrate
fi

exec php-fpm
