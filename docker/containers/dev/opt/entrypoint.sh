#!/bin/bash

# Base entrypoint for dev launch

set -xe

/opt/install_dependencies.sh

cat /etc/nginx/nginx.conf.template  | sed "s|_NGINX_WEB_ROOT|${NGINX_WEB_ROOT}|g" > /etc/nginx/nginx.conf
/usr/sbin/nginx -g 'daemon on; master_process on;'
php-fpm
