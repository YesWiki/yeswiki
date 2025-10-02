#!/bin/bash

set -xe

cd /var/www/html
composer install
export COREPACK_ENABLE_DOWNLOAD_PROMPT=0
yarn install
./yeswicli migrate
php-fpm
