#!/bin/bash
cd /var/www/html
composer install
source /home/yeswiki/.nvm/nvm.sh
nvm use 20
corepack enable
yarn install
./yeswicli migrate
php-fpm
