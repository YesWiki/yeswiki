#!/bin/bash

# Reset environment for tests

set -e

rm -f  /var/www/html/test.config.php
echo "DROP DATABASE IF EXISTS yeswiki_test; CREATE DATABASE yeswiki_test;" |  mysql -h yeswiki-db -u root -proot --skip-ssl
curl  --silent --fail --show-error  \
          -F "config[default_language]=fr" \
          -F "config[wakka_name]=MyTestWiki" \
          -F "config[root_page]=PagePrincipale" \
          -F "config[base_url]=http://localhost/?" \
          -F "config[mysql_host]=yeswiki-db" \
          -F "config[mysql_database]=yeswiki_test" \
          -F "config[mysql_user]=root" \
          -F "config[mysql_password]=root" \
          -F "config[table_prefix]=yeswiki_" \
          -F "config[allow_raw_html]=1" \
          -F "config[archive][privatePath]=./private/archives" \
          -F "admin_name=WikiAdmin" \
          -F "admin_password=WikiAdminPassword" \
          -F "admin_password_conf=WikiAdminPassword" \
          -F "admin_email=test@example.com" \
          -F "submit=Continue" \
          "http://localhost/?PagePrincipale&installAction=install"
#/var/www/html/yeswicli migrate
