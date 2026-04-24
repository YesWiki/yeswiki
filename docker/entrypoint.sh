#!/bin/bash
cd /var/www/html
composer install
source /home/yeswiki/.nvm/nvm.sh
nvm use 22
corepack enable
yarn install
./yeswicli migrate
php-fpm
