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
RUN apt update && apt install -y --no-install-recommends libasound2t64 libatk-bridge2.0-0t64 libatk1.0-0t64 libatspi2.0-0t64 libcairo2 libcups2t64 libdbus-1-3 libdrm2 libgbm1 libglib2.0-0t64 libnspr4 libnss3 libpango-1.0-0 libx11-6 libxcb1 libxcomposite1 libxdamage1 libxext6 libxfixes3 libxkbcommon0 libxrandr2 libasound2 libcairo-gobject2 libdbus-glib-1-2 libfontconfig1 libfreetype6 libgdk-pixbuf-2.0-0 libgtk-3-0t64 libharfbuzz0b libpangocairo-1.0-0 libx11-xcb1 libxcb-shm0 libxcursor1 libxi6 libxrender1 libxtst6 libsoup-3.0-0 gstreamer1.0-libav gstreamer1.0-plugins-bad gstreamer1.0-plugins-base gstreamer1.0-plugins-good libegl1 libenchant-2-2 libepoxy0 libevdev2 libgles2 libglx0 libgstreamer-gl1.0-0 libgstreamer-plugins-base1.0-0 libgstreamer1.0-0 libgtk-4-1 libgudev-1.0-0 libharfbuzz-icu0 libhyphen0 libicu76 libjpeg62-turbo liblcms2-2 libmanette-0.2-0 libnotify4 libopengl0 libopenjp2-7 libopus0 libpng16-16t64 libproxy1v5 libsecret-1-0 libwayland-client0 libwayland-egl1 libwayland-server0 libwebp7 libwebpdemux2 libwoff1 libxml2 libxslt1.1 libatomic1 libevent-2.1-7t64 libavif16 xvfb fonts-noto-color-emoji fonts-unifont xfonts-scalable fonts-liberation fonts-ipafont-gothic fonts-wqy-zenhei fonts-tlwg-loma-otf fonts-freefont-ttf &&\
 rm -rf /var/lib/apt/lists/*

USER yeswiki

CMD ["/opt/entrypoint_test.sh"]