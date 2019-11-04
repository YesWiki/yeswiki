FROM lavoweb/php-7.3

# Add MySQLi
RUN docker-php-ext-install mysqli

# Add Chromium browser to enable pdf creation
RUN rm -rf /var/cache/apk/* && rm -rf /tmp/* && \
    apt install -y --no-install-recommends chromium-browser