# One wiki with Docker Compose

`compose.yaml` runs one wiki on one machine from the published image, with MariaDB beside it. The
wiki's directory and the database each live in a volume, and those two volumes are what you back up.

```
cp .env.example .env    # then set every value in it
docker compose up -d
```

On its first start the container installs the wiki with the values from `.env`, including the first
account (`ADMIN_*`). On every start it migrates the wiki to the image's version, then serves it on
port 8080 of the container, published on `LISTEN` (`127.0.0.1:8080` by default).

Put a reverse proxy in front of it for HTTPS, and tell the wiki to believe it: add
`TRUSTED_PROXIES` (the proxy's address or network, for example `172.16.0.0/12`) to the `wiki`
service's environment.

## Updating

Set `YESWIKI_IMAGE` to the new version (`ghcr.io/yeswiki/yeswiki:5.1`, or `:latest`), then:

```
docker compose pull
docker compose up -d
```

The new container migrates the wiki before it serves it.

## Adding extensions

The published image carries no extension. To add some, build an image on top of it, in a
`Dockerfile` beside `compose.yaml`:

```
FROM ghcr.io/yeswiki/yeswiki:5.1
USER root
RUN yeswiki-entrypoint bundle lms ferme
USER 10001:10001
```

`bundle` fetches them from the repository of the image's release line, with the same steps as the
update screen, and fails the build when one of them cannot be installed. Then replace `image:` by
`build: .` in `compose.yaml`, and switch the extensions on in the `wiki` service's environment:

```
      ACTIVE_EXTENSIONS: '["lms","ferme"]'
```

An extension installed this way is part of the image: the update screen lists it, and installing,
updating or removing one is done by building the image again.

## What the container is

- It runs as uid 10001, on a read-only root filesystem. `/srv/wiki` (the volume) and `/tmp` are the
  only places it writes.
- It answers `/healthz` when PHP runs and `/readyz` when the database and the storage answer.
  Docker's own healthcheck uses `/healthz`.
- It runs as many PHP workers as `YESWIKI_WORKERS` says, each holding a database connection while
  it serves a request, and none while it waits (`YESWIKI_DB_KEEP_CONNECTION=1` keeps it open).
  Left unset, FrankenPHP starts two per CPU the host has, which a container's CPU limit does not
  lower: set it on a large machine.
- Its tags are `5.1.2`, `5.1` and `latest`. A pre-release (`5.1.0-beta1`) gets its own tag and moves
  neither `5.1` nor `latest`.
- It is signed by the release workflow. To check a tag before you run it:

```
cosign verify ghcr.io/yeswiki/yeswiki:5.1 \
  --certificate-identity-regexp 'https://github.com/YesWiki/yeswiki/.github/workflows/release.yml@.*' \
  --certificate-oidc-issuer https://token.actions.githubusercontent.com
```

For several replicas of one wiki behind a load balancer, see "Plusieurs serveurs pour un même
wiki" in `docs/fr/admin.md`: there the migrations run as a job, not on every start.
