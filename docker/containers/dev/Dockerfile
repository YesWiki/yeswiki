# Yeswiki dev image
FROM php:8.2-fpm

COPY --from=mlocati/php-extension-installer:2.9 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions gd mysqli zip intl

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt install --no-install-recommends -y nodejs  && \
    apt install --no-install-recommends -y git  && \
    corepack enable


# RUN apt-get update && \
#     apt-get install -y libfreetype-dev libjpeg-dev libpng-dev libwebp-dev libzlcore-dev libzip-dev git unzip mariadb-client  && \
#     # Playwright dependencies
#     #apt-get install -y libglib2.0-0 libnss3 libnspr4 libdbus-1-3 libatk1.0-0 libatk-bridge2.0-0 libatspi2.0-0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libxkbcommon0 libasound2 libcups2 libpango-1.0-0 libcairo2 --no-install-recommends && \
#     docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
#     docker-php-ext-install mysqli gd zip && \
#     docker-php-source delete && \
#     apt clean && \
#     docker-php-source delete  && \
#     rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# COPY --from=nginx:latest /usr/sbin/nginx /usr/sbin/nginx
# COPY --from=nginx:latest /etc/nginx/ /etc/nginx/
# RUN mkdir -p /var/log/nginx
#

ARG UID

ARG GID

RUN groupadd -g "${GID}" yeswiki \
&& useradd --no-log-init --create-home -u "${UID}" -g "${GID}" -G www-data yeswiki

USER yeswiki

