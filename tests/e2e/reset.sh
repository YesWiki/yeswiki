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

php /var/www/html/src/commands/console core:install --no-interaction \
          --driver="${DRIVER}" \
          --db-host="${DB_HOST}" \
          --db-database="${DB_NAME}" \
          --db-user="${DB_USER}" \
          --db-password="${DB_PASSWORD}" \
          --table-prefix=yeswiki_ \
          --base-url="http://yeswiki-web/?" \
          --root-page=PagePrincipale \
          --wiki-name=MyTestWiki \
          --language=fr \
          --other-languages=en,es \
          --allow-raw-html \
          --admin-name=WikiAdmin \
          --admin-email=test@example.com \
          --admin-password=WikiAdminPassword
/var/www/html/yeswicli migrate
