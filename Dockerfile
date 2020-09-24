FROM lavoweb/php-7.3:composer

# Add MySQLi
RUN docker-php-ext-install mysqli

# Add Chromium browser to enable pdf creation
RUN apt-get update \
    && apt install -y --no-install-recommends chromium \
    && rm -rf /var/cache/apk/* \
    && rm -rf /tmp/*

# Add default theme
RUN mkdir -p themes/margot \
    && curl -o - -sSL https://github.com/YesWiki/yeswiki-theme-margot/archive/master.tar.gz \
        | tar xzfv - --strip-components 1 -C themes/margot


# Enable UI plugins/theme updates
RUN composer install --working-dir tools/autoupdate
