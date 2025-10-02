#!/bin/bash

set -xe

/opt/install.sh

/usr/sbin/nginx -g 'daemon on; master_process on;'
php-fpm
