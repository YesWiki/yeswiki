# The Docker development stack

This directory holds the development stack, the browser-suite overlay and the production image.
To run a wiki in production with Docker, see [`deploy/compose/`](../deploy/compose/README.md).

| File | What it is |
| --- | --- |
| `dockerfile`, `docker-compose.yml`, `entrypoint.sh`, `.env.example` | The dev stack: php-fpm on your checkout |
| `docker-compose-test.yml`, `entrypoint_test.sh` | The overlay that adds the Playwright UI |
| `image.Dockerfile`, `image-entrypoint.sh`, `smoke-image.sh` | The production image, built by the release workflow |

## The dev stack

| Service | What it is | Address |
| --- | --- | --- |
| `yeswiki-app` | php-fpm with Node 24 and Yarn, your checkout mounted at `/var/www/html` | |
| `yeswiki-web` | nginx in front of it (`nginx.conf`) | <http://localhost:8085> |
| `yeswiki-db` | MySQL 8.4, as `yeswiki-db` from the app | |
| `yeswiki-pg` | PostgreSQL 16, as `yeswiki-pg` (user, password, database: `yeswiki`) | |
| `yeswiki-s3` | SeaweedFS with its S3 gateway | <http://localhost:8333> |
| `myadmin` | phpMyAdmin | <http://localhost:8086> |
| `mail` | Mailpit, which catches every mail the wiki sends | <http://localhost:1080> |

Every port listens on `127.0.0.1` only. With the default project name, the containers are called
`yeswiki-app`, `yeswiki-web`, `yeswiki-db` and so on.

### Starting it

Run everything from this directory. The app runs as your own user, so the files it writes in your
checkout stay yours. Create `docker/.env` from the example, then set `UID` to the value of `id -u`
and `GID` to the value of `id -g`:

```
cp .env.example .env
```

Then build and start:

```
docker compose build
docker compose up -d
```

The first start runs `composer install` and `yarn install`. Later starts run them again only when
`composer.lock` or `yarn.lock` changed, and run `./yeswicli migrate` once a wiki is installed.

On the install page, use:

- **Database host**: `yeswiki-db`
- **Database name**: `yeswiki`
- **User name**: `yeswiki`
- **Password**: `password`

These come from `yeswiki.secrets`.

Mailpit is set up like a production relay: submission on port 587, STARTTLS required, user
`yeswiki` with password `password`. Its certificate is self-signed, which is the one difference
from a real relay: the wiki must not check it. To send mail to it, add this to
`yeswiki.config.php`:

```
'contact_mail_func' => 'smtp',
'contact_smtp_host' => 'mail',
'contact_smtp_port' => '587',
'contact_smtp_secure' => 'tls',
'contact_smtp_user' => 'yeswiki',
'contact_smtp_pass' => 'password',
'contact_smtp_verify_peer' => false,
```

### Running several stacks side by side

A stack uses the project name `yeswiki` and the ports 8085, 8086, 1080 and 8333 by default, so a
second one would conflict with the first. To run several at once, use one checkout per stack and set
a project name and free ports in the `.env` of each additional one:

```
COMPOSE_PROJECT_NAME=yeswiki-other
YESWIKI_WEB_PORT=8185
YESWIKI_MYADMIN_PORT=8186
YESWIKI_MAIL_PORT=1180
YESWIKI_S3_PORT=8433
```

The project name also names the containers and volumes of that stack: use `yeswiki-other-app` in the
`docker exec` commands of this page, and `yeswiki-other-db` and `yeswiki-other-pg` in the
`docker volume rm` ones. `docker compose` commands and the service names the containers use between
themselves, such as `yeswiki-db` on the install page, stay the same.

The test overlay publishes the fixed port 8083, so only one test stack can run at a time.

### Dev container

`.devcontainer/devcontainer.json` runs this same stack from an editor that supports dev containers:
use "Reopen in Container" in VS Code. It creates `docker/.env` with your `UID` and `GID` if the file
does not exist yet, and leaves an existing one alone.

The editor attaches to `yeswiki-app` as soon as the container starts, while `entrypoint.sh` may still
be running `composer install` and `yarn install`. On the first launch, wait for them to finish before
using the wiki.

### Starting over

Remove `yeswiki.config.php` to get the install page back. To empty the databases as well:

```
docker compose down
docker volume rm yeswiki-db yeswiki-pg
```

### Updating dependencies

```
docker compose exec yeswiki-app composer install
docker compose exec yeswiki-app yarn install
```

## The browser suite

The overlay starts the Playwright UI beside the dev stack. The specs are in `tests/e2e`.

```
docker compose -f docker-compose.yml -f docker-compose-test.yml up -d --build
```

The UI is at <http://localhost:8083>. To run the suite against PostgreSQL instead of MySQL:

```
docker exec -e YESWIKI_TEST_DRIVER=pgsql yeswiki-app bash tests/e2e/reset.sh
docker exec -e PLAYWRIGHT_BROWSERS_PATH=0 -e YESWIKI_BASE_URL=http://yeswiki-web -e YESWIKI_TEST_DRIVER=pgsql \
  yeswiki-app npx playwright test
```

## The production image

`image.Dockerfile` packs the release's static binary: no PHP is compiled there. The release workflow
builds it for amd64 and arm64 from the binaries of the same tag, tries it with `smoke-image.sh`,
then pushes it to `ghcr.io/yeswiki/yeswiki` and `docker.io/yeswiki/yeswiki` and signs it with cosign.

To build it from a binary you built yourself with `binary/build-static.sh`, with or without
extensions:

```
docker build -f docker/image.Dockerfile -t yeswiki:local .
docker build -f docker/image.Dockerfile --build-arg EXTENSIONS="lms ferme" -t yeswiki:local-lms .
docker/smoke-image.sh yeswiki:local
```

`EXTENSIONS` runs `yeswiki-entrypoint bundle`, the same as an image built `FROM` the published one
(see `deploy/compose/README.md`), which runs `./yeswicli core:bundle` in the program tree.

`image-entrypoint.sh` reads three variables itself: `PORT` (8080), `YESWIKI_WORKERS` (passed to
`yeswiki serve` as `--workers` when set) and `YESWIKI_REPLICATED=1` (do not migrate on start). The
rest of the environment is the wiki's. A first start on an empty database creates the wiki, with an
admin account when `ADMIN_*` are set; `YESWIKI_INSTALL=1`, which used to allow it, is no longer
needed and is ignored.
