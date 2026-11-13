# The yeswiki chart

One YesWiki on several replicas behind a Service, with no sticky sessions. The chart ships no
database and no bucket: it connects to the ones you give it.

```
helm install mywiki deploy/helm/yeswiki \
  --set baseUrl='https://wiki.example.org/?' \
  --set database.host=mariadb.db.svc --set database.password=… \
  --set storage.bucket=mywiki --set storage.endpoint=https://s3.example.org \
  --set storage.publicUrl=https://s3.example.org/mywiki --set storage.key=… --set storage.secret=… \
  --set install.adminEmail=admin@example.org --set install.adminPassword=…
```

## What runs

- **A Deployment** of `replicaCount` pods (3 by default). Each pod runs the image's wiki directory,
  with an `emptyDir` over each folder it writes to (`cache`, `files`, `private`). It has no
  configuration file: the host's settings, `baseUrl` and `rewriteMode` among them, are
  environment variables, and the wiki's own settings are in the database, so the replicas share
  all of them. What an image built `FROM` this one puts in `custom/` (themes, `custom/templates`,
  PHP) stays visible, read-only.
- **An install Job** before the first `helm install` starts anything else. It creates the wiki in
  the empty database, reading its credentials from a Secret of its own that goes once it succeeded,
  since the chart's Secret does not exist yet at that point.
- **A migrate Job** before every `helm upgrade`, with the new image. While it runs and until the
  rollout ends, a replica whose code and database disagree serves reads and refuses writes with a
  503 and `Retry-After`.
- **A Service**, an optional **Ingress**, and a **PodDisruptionBudget** when there is more than one
  replica.

Each pod runs as uid 10001 on a read-only root filesystem, with every capability dropped, and
without the `<SERVICE>_PORT` variables Kubernetes injects by default (`enableServiceLinks: false`):
a Service named `db` would otherwise hand the wiki a `DB_PORT` of `tcp://…`. It
consumes the wiki's queue itself (`YESWIKI_QUEUE_CONSUMER=worker`). On SIGTERM it lets the
consumers finish their message (10s) and the requests in flight finish (15s), after a 5s `preStop`
that lets the Service stop sending it traffic first.

## Values

| Value | Default | What it is |
| --- | --- | --- |
| `image.repository`, `image.tag` | `ghcr.io/yeswiki/yeswiki`, the chart's `appVersion` | The image. Build one `FROM` it to add extensions (`deploy/compose/README.md`). |
| `replicaCount` | `3` | How many replicas serve the wiki |
| `workers` | `4` | PHP workers per replica, each holding a database connection while it serves a request. Left empty, FrankenPHP starts two per CPU the node has, whatever the pod's limit. |
| `podAnnotations` | `{}` | Annotations of the pods; changing one restarts them |
| `baseUrl` | required | The address the wiki answers on, with its trailing `?` unless `rewriteMode` is on |
| `rewriteMode` | `false` | Addresses without `?` |
| `trustedProxies` | the private networks | Whose `X-Forwarded-*` headers the wiki believes: the ingress controller's |
| `activeExtensions` | `[]` | The extensions of the image this wiki runs |
| `env` | `{}` | Any other operator setting, by its environment name (`CONTACT_MAIL_FUNC: smtp`) |
| `database.driver` | `mysql` | `mysql` or `pgsql`; SQLite is refused |
| `database.host`, `port`, `name`, `user`, `password`, `tablePrefix` | host required | The database |
| `storage.bucket`, `publicUrl` | required | The bucket, and where its public objects are read |
| `storage.endpoint`, `region`, `pathStyle`, `prefix`, `key`, `secret` | | The rest of the S3 access; `pathStyle: true` for SeaweedFS and MinIO-like servers |
| `existingSecret` | | A Secret of yours, in place of the chart's: see below |
| `secretEnv` | `{}` | Further secret environment (`CONTACT_SMTP_PASS`) |
| `install.enabled` | `true` | Install the wiki after the first `helm install`. Turn it off to take over a database that already holds one. |
| `install.adminName`, `adminEmail`, `adminPassword` | email required | The first account |
| `migrate.enabled` | `true` | Migrate before every upgrade |
| `service.*`, `ingress.*` | ClusterIP on 80, no ingress | How the wiki is reached; `service.nodePort` fixes the port of a `NodePort` Service |
| `resources`, `nodeSelector`, `tolerations`, `affinity` | | Scheduling |
| `podDisruptionBudget.minAvailable` | `1` | |
| `terminationGracePeriodSeconds` | `40` | Leave it above 30: the binary's own stop takes up to 25s after the 5s `preStop` |

### The Secret

The chart creates one holding `DB_PASSWORD`, `YESWIKI_S3_KEY`, `YESWIKI_S3_SECRET`, `ADMIN_PASSWORD`
and two keys it generates on the first install and keeps on every upgrade: `YESWIKI_SIGNING_KEY`,
which signs the login cookie and the CSRF tokens, and `YESWIKI_BOT_GUARD_KEY`. Every replica has to
share them; a wiki left to create its own would create one per pod.

With `existingSecret`, the chart creates none and reads those names from yours. Generate the two keys
with `openssl rand -hex 32`.

## The database

Any MySQL 8.4+, MariaDB 11+ or PostgreSQL 16+ that every replica can reach: a managed one,
CloudNativePG, mariadb-operator.

**Do not put PgBouncer in transaction mode in front of PostgreSQL.** The wiki's locks (migrations,
the queue's schedule, archives) are session-level advisory locks, which a transaction pooler hands
to another client between two statements. Use PgBouncer in session mode, or no pooler and a
`max_connections` large enough for every worker of every replica.

A replica holds a connection only while it works. A worker opens one for a request and gives it
back before waiting for the next, unless a lock or a transaction still needs it. The queue consumer
leaves once its queue is empty and starts again a minute later. So an idle replica holds none. At
the peak, a replica holds one per busy worker, one for its consumer and one per probe in flight,
and a rolling update runs old and new pods side by side. Count `(replicaCount + 1) × (workers + 2)`,
plus the migrate Job's one: 37 with the defaults, under MariaDB's 151 and PostgreSQL's 100.
`YESWIKI_DB_KEEP_CONNECTION=1` in `env` keeps each worker's connection open, as before.

## The bucket

It has to exist before the first install, and its policy must allow anonymous reads of the public
prefixes and nothing else: see "Stockage S3" in `docs/fr/admin.md`.
