#!/bin/bash

cd /var/www/html
source /home/yeswiki/.nvm/nvm.sh
nvm use 20

yarn playwright test