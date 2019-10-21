FROM lavoweb/php-7.3

# Add MySQLi
RUN docker-php-ext-install mysqli
