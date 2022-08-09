FROM lavoweb/php-7.3:composer

# Add MySQLi
RUN docker-php-ext-install mysqli

# Add Chromium browser to enable pdf creation
RUN apt-get --allow-releaseinfo-change update && apt install -y --no-install-recommends \
    chromium \
    git
RUN rm -rf /var/cache/apk/* \
    rm -rf /tmp/*

# Add default theme
RUN mkdir -p themes/margot \
    && curl -o - -sSL https://github.com/YesWiki/yeswiki-theme-margot/archive/master.tar.gz \
        | tar xzfv - --strip-components 1 -C themes/margot

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Node & NPM & Yarn
RUN curl -sL https://deb.nodesource.com/setup_14.x  | bash -
RUN apt-get install -y --no-install-recommends nodejs
RUN curl -L https://npmjs.org/install.sh | sh
RUN npm install -g yarn

# Allow container to write wakka.config.php
RUN chmod +wx .