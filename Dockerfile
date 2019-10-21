FROM lavoweb/php-7.3

# Add MySQLi
RUN docker-php-ext-install mysqli

RUN rm -rf /var/cache/apk/* && rm -rf /tmp/* && \
    curl --insecure https://getcomposer.org/composer.phar -o /usr/bin/composer && chmod +x /usr/bin/composer
