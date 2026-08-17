#!/bin/bash

set -e

# A fresh wiki for the next spec.
#
# Driver-aware since PostgreSQL joined the compose stack: ADR-0015 commits the search index
# to all three dialects, and the only way "supported" and "tested" can mean the same thing is
# if the browser suite can be pointed at each of them.
#
#   bash tests/e2e/reset.sh                            # MySQL, the default -- unchanged
#   YESWIKI_TEST_DRIVER=pgsql bash tests/e2e/reset.sh
#
# The installer takes `config[db_*]` keys; the historical `config[mysql_*]` spellings still
# work through InstallationController::LEGACY_KEY_MAPPING, but naming the driver explicitly
# is rather the point here.

DRIVER="${YESWIKI_TEST_DRIVER:-mysql}"

rm -f /var/www/html/test.config.php

case "$DRIVER" in
  mysql)
    DB_HOST=yeswiki-db
    DB_USER=root
    DB_PASSWORD=root
    DB_NAME=yeswiki_test
    echo "DROP DATABASE IF EXISTS ${DB_NAME}; CREATE DATABASE ${DB_NAME};" \
      | mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASSWORD}" --skip-ssl
    ;;
  pgsql)
    DB_HOST=yeswiki-pg
    DB_USER=yeswiki
    DB_PASSWORD=password
    DB_NAME=yeswiki_test
    export PGPASSWORD="${DB_PASSWORD}"
    # Connected to `template1`, not to the database being dropped -- psql has to be attached
    # to something else to drop it. WITH (FORCE) terminates whatever connection php-fpm is
    # still holding; without it a reset between specs fails with "database is being accessed
    # by other users", which MySQL never does.
    psql -h "${DB_HOST}" -U "${DB_USER}" -d template1 -q \
      -c "DROP DATABASE IF EXISTS ${DB_NAME} WITH (FORCE);" \
      -c "CREATE DATABASE ${DB_NAME};"
    ;;
  *)
    echo "unknown YESWIKI_TEST_DRIVER '${DRIVER}' (expected mysql or pgsql)" >&2
    exit 1
    ;;
esac

curl --silent --fail --show-error \
          -F "config[default_language]=fr" \
          `# two more languages, so the suite covers a wiki that offers a choice: the` \
          `# language switcher only exists where there is something to switch to, and` \
          `# this is also the only place the installer's own handling of the list runs` \
          -F "config[other_languages][]=en" \
          -F "config[other_languages][]=es" \
          -F "config[wakka_name]=MyTestWiki" \
          -F "config[root_page]=PagePrincipale" \
          -F "config[base_url]=http://yeswiki-web/?" \
          -F "config[db_driver]=${DRIVER}" \
          -F "config[db_host]=${DB_HOST}" \
          -F "config[db_database]=${DB_NAME}" \
          -F "config[db_user]=${DB_USER}" \
          -F "config[db_password]=${DB_PASSWORD}" \
          -F "config[table_prefix]=yeswiki_" \
          -F "config[allow_raw_html]=1" \
          -F "config[archive][privatePath]=./private/archives" \
          -F "admin_name=WikiAdmin" \
          -F "admin_password=WikiAdminPassword" \
          -F "admin_password_conf=WikiAdminPassword" \
          -F "admin_email=test@example.com" \
          -F "submit=Continue" \
          "http://yeswiki-web/?PagePrincipale&installAction=install"
/var/www/html/yeswicli migrate
