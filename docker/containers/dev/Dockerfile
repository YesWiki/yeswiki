FROM node:20-slim AS node
RUN rm /usr/local/bin/docker-entrypoint.sh

# Yeswiki dev image
FROM php:8.2-fpm AS base

COPY ./opt /opt

#Install nodejs and corepack
COPY --from=node /usr/local/bin /usr/local/bin
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN npm install -g corepack && corepack enable

# Install other dependencies
RUN apt-get update && \
    apt-get install -y git unzip mariadb-client netcat-openbsd inetutils-ping gettext-base &&\
    rm -rf /var/lib/apt/lists/*

# Install php extensions
COPY --from=mlocati/php-extension-installer:2.9 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd mysqli zip intl

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

COPY --from=nginx:1.29 /usr/sbin/nginx /usr/sbin/nginx
COPY --from=nginx:1.29 /etc/nginx/ /etc/nginx/
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
COPY ./nginx.conf.template /etc/nginx/nginx.conf.template
RUN rm -f /etc/nginx/nginx.conf && chown yeswiki:yeswiki /etc/nginx

USER yeswiki

CMD ["/opt/entrypoint.sh"]

FROM base AS test

USER root
# Install playwright dependencies
RUN apt update && apt install -y libglib2.0-0t64 \
             libnspr4 \
             libnss3 \
             libdbus-1-3 \
             libatk1.0-0t64 \
             libatk-bridge2.0-0t64 \
             libatspi2.0-0t64 \
             libxcomposite1\
             libxdamage1\
             libxfixes3\
             libxrandr2\
             libgbm1\
             libxkbcommon0\
             libasound2t64 && \
 rm -rf /var/lib/apt/lists/*

USER yeswiki

CMD ["/opt/entrypoint_test.sh"]