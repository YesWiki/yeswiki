FROM node:20-slim AS node
RUN rm /usr/local/bin/docker-entrypoint.sh

# Yeswiki dev image
FROM php:8.2-fpm

COPY ./opt /opt

#Install nodejs and corepack
COPY --from=node /usr/local/bin /usr/local/bin
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN npm install -g corepack && corepack enable

COPY --from=mlocati/php-extension-installer:2.9 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions gd mysqli zip intl

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

COPY --from=nginx:1.29 /usr/sbin/nginx /usr/sbin/nginx
COPY --from=nginx:1.29 /etc/nginx/ /etc/nginx/
COPY ./nginx.conf /etc/nginx/nginx.conf
RUN mkdir -p /var/log/nginx
RUN mkdir -p /var/cache/nginx
RUN mkdir -p /var/run/nginx/

ARG UID
ARG GID

RUN groupadd -g "${GID}" yeswiki \
&& useradd --no-log-init --create-home -u "${UID}" -g "${GID}" -G www-data yeswiki

RUN chown -R yeswiki:yeswiki /var/log/nginx
RUN chown -R yeswiki:yeswiki /var/cache/nginx
RUN chown -R yeswiki:yeswiki /var/run/nginx/

USER yeswiki

