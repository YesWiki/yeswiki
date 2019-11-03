FROM lavoweb/php-7.3

# Add MySQLi
RUN docker-php-ext-install mysqli

RUN rm -rf /var/cache/apk/* && rm -rf /tmp/* && \
    curl --insecure https://getcomposer.org/composer.phar -o /usr/bin/composer && chmod +x /usr/bin/composer && \
    curl -sSL https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb --output google-chrome.deb && \
    apt install -y --no-install-recommends ./google-chrome.deb && \
    rm google-chrome.deb
