# YesWiki installation

Not much to it (as long as it works, ahem). Unpack/upload the distribution files
into a directory that can be accessed via the web. Then go to the corresponding URL.
A web-based installer will walk you through the rest.

**Requirements**: PHP >= 8.3, and a MySQL/MariaDB, PostgreSQL or SQLite database.

PostgreSQL must be **12 or newer**: the search index is a generated column
(`GENERATED ALWAYS AS (...) STORED`), which PostgreSQL 11 and older reject at install. CI tests
PostgreSQL 16.

### PHP extensions

`composer.json` is the authority and `ExtensionManifestTest` keeps it honest: every extension core
calls into is named there, and this list is checked against it.

**Required** — the wiki does not run without these:

```
ctype
curl
dom
fileinfo
filter
gd
hash
iconv
json
libxml
mbstring
openssl
pcre
pdo
session
simplexml
tokenizer
xml
xmlreader
zip
```

**A database driver** — at least one, matching the database you are pointing the wiki at. The
installer only offers a driver whose extension is loaded:

| extension    | database                                            |
| ------------ | --------------------------------------------------- |
| `pdo_mysql`  | MySQL / MariaDB                                     |
| `pdo_pgsql`  | PostgreSQL                                          |
| `pdo_sqlite` | SQLite — no server to set up, good for a small wiki |

**Optional** — core checks for each at run time and does without, at a cost:

| extension      | what it buys you                                                                              |
| -------------- | --------------------------------------------------------------------------------------------- |
| `imap`         | the IMAP importer can read a mailbox (with `php-imap/php-imap`)                               |
| `intl`         | search folds accents across more scripts; without it, an `iconv` transliteration covers fewer |
| `zend-opcache` | saving a setting takes effect at once instead of when the opcode cache next revalidates       |

Most of the required list is compiled into any distribution's PHP. The ones a shared host
sometimes leaves out are `gd`, `zip` and `intl`. To check a server before installing:

```sh
php -m
```

and from a git checkout, once `composer install` has run, this checks the whole set including what
the vendor libraries need:

```sh
composer check-platform-reqs
```

**Important**:
If checked out from git repository, you need to install deps via **both** package managers.  
So after downloading/synchroning files on your server, run `composer install` **and**
`yarn install`.  
You can find information about installation of `composer` [here](https://getcomposer.org).

`yarn install` is not optional for a git checkout: `javascripts/vendor/` and `styles/vendor/` are
gitignored and populated by its `postinstall` hook, so without it htmx, Ace and Leaflet are simply
absent and the wiki loads without its editors or maps.

**Upgrading an existing wiki** rather than installing a new one? See [UPGRADE.md](UPGRADE.md).

## Web installer example

If your site <https://mysite.com> is mapped to the directory `/home/jdoe/www`,  
and you place the YesWiki files into `/home/jdoe/www/wiki`, you should go to  
<https://mysite.com/wiki>.

Detailed instructions are available [in the official doc](https://yeswiki.net/?doc#/docs/fr/webmaster?id=installation).

## The first page

A new wiki holds only what it needs to run: the sandbox, the header and footer, the two menus and
the Page, Account and File forms. `files/` is empty. The home page does not exist yet, so the first
time an administrator opens it they are asked what the wiki is for: tick any of the Annuaire,
Agenda, Blog and Ressources forms, or start from an empty wiki. Visitors are told the wiki is being
set up until that choice is made.

From a terminal, `./yeswicli onboarding:apply annuaire agenda` makes the same choice,
`--all` takes every form, and no argument at all starts empty.

## Housekeeping

Everything a wiki does outside a page view goes through one queue in its own database: reindexing
search, delivering ActivityPub activities, mailing digests, syncing data sources, backups and
restores, and the periodic cleanup (old page revisions and Journal entries, expired recovery and
activation keys, the bot guard's counters). Something has to consume that queue, and
`YESWIKI_QUEUE_CONSUMER` says what. There are six modes:

| Mode            | What consumes the queue                                                                | You set up                     |
| --------------- | -------------------------------------------------------------------------------------- | ------------------------------ |
| `request`       | the end of each page view, once the page has been sent                                 | nothing (the default)          |
| `request-spawn` | a detached `messenger:drain` that the end of a page view starts, at most once a minute | `proc_open` allowed to PHP     |
| `command`       | a crontab line running `messenger:drain`                                               | a crontab                      |
| `api`           | an HTTP call to `/api/queue/consume`, POST or GET (online cron, monitor)               | a call, and a secret           |
| `worker`        | a process that never returns, `messenger:consume`, kept alive by systemd or similar    | a service                      |
| `binary`        | the YesWiki binary, which supervises one `messenger:consume` per wiki                  | nothing (the default under it) |

Nothing has to be chosen: a wiki that says nothing consumes in `request` mode (in `binary` mode
under the binary), which is what keeps a wiki that is installed and forgotten working on shared
hosting with no shell and no cron. The cost is that work only moves when someone visits, so a quiet
wiki lags, and where PHP cannot send the page before the script ends (mod_php, CGI: the PHP that runs inside Apache, or as a plain CGI program) the visitor
waits for it, within a budget of a few seconds. A crontab or a worker has neither problem.

The mode is read from the real environment, then from `private/.env` (see `.env.example`; the only
option on a host where you cannot set environment variables), then from `queue_consumer` in
`yeswiki.config.php`, and falls back to the default:

```sh
YESWIKI_QUEUE_CONSUMER=command
```

The binary does not set this variable, it only marks the wiki as served by it, so a wiki of a farm
can still choose another mode. `/admin/jobs` shows the mode in force and where the setting was
read. That page and `/admin/health` raise an alert when jobs wait and nothing consumes them, when
the value is not a mode, or when the mode needs something that is missing; each alert is explained
in [the admin documentation](docs/fr/async.md).

A pass spends what the server allows a request (`max_execution_time`, `memory_limit`) less a
margin, and only one pass runs at a time per wiki. A job that cannot finish inside that is cut and
moved to the failed jobs, with the advice to switch to `worker` or `binary`, where nothing cuts it.

### `command`: a crontab

```
*/5 * * * * cd /home/jdoe/www/wiki && ./yeswicli messenger:drain
```

`messenger:drain` first queues the scheduled tasks that are due, then consumes until the queue is
empty or the time is spent, and never waits for more. `--budget=<seconds>` shortens the time it may
take (it never goes past what PHP allows) and `--no-schedule` leaves the scheduled tasks alone. It
exits 0 when the pass ended normally, 1 when it ended on an error and 2 on a bad option, so cron
mails you about a failure. Every few minutes is the right pace: in this mode the alert for "nothing
consumes" only rises after three hours.

A crontab that already calls `./yeswicli core:maintenance` keeps working: the command is a
deprecated alias of `messenger:drain` and says so. A wiki that had `'maintenance_trigger' => 'cron'`
and chose no mode is moved to `command` by `./yeswicli migrate`; the setting itself is gone. That
crontab now carries every background job, not only the housekeeping: if it ran hourly, as the old
instructions said, make it every few minutes as above. A `MAINTENANCE_TRIGGER` line in
`private/.env` does nothing any more: remove it, and put `YESWIKI_QUEUE_CONSUMER=command` there
if that crontab is what should consume.

### `api`: an online cron

For hosts that offer a web cron but no crontab. Open `/admin/jobs` while the mode is `api` and
press "Générer un secret": it is written to the wiki's configuration as `queue_consume_secret` and
shown once. Then have the service call:

```sh
curl -X POST -H 'X-YesWiki-Queue-Secret: THE-SECRET' 'https://mysite.com/wiki/?api/queue/consume'
```

If the service can only schedule a GET without custom headers, as some hosts' crons do, put the
secret in the URL instead. Calling a GET that changes things bends HTTP on purpose, for those hosts.
The secret then ends up in access logs, so prefer the header when the service allows it:

```sh
curl 'https://mysite.com/wiki/?api/queue/consume&secret=THE-SECRET'
```

The route answers `401` to a missing or wrong secret (and to everybody while none is set), `409`
when another pass is already running (call again later, nothing is wrong), and `200` otherwise with
`{"handled": 3, "remaining": 0, "durationMs": 412, "stopReason": "empty"}`. Every few minutes is
enough, and a call takes at most about 50 seconds. Replacing the secret from the same page cuts off
the old one at once.

### `worker`: a process that stays up

`messenger:consume` waits for messages and runs them as they come, and queues the scheduled tasks
itself. It stops after `--time-limit` seconds (default 3600), when its memory passes
`--memory-limit` (default 128M), or after `--limit` messages, and the process manager starts it
again.

First tell the wiki itself that this is the mode, where the web side reads it: put
`YESWIKI_QUEUE_CONSUMER=worker` in `private/.env` (or `'queue_consumer' => 'worker'` in
`yeswiki.config.php`). The service below does not need to carry the variable, and one set only in
the unit would never reach php-fpm or Apache: visitors would keep consuming at the end of their page
views, and `/admin/jobs` would show `request`. Then a systemd unit,
`/etc/systemd/system/yeswiki-queue.service`:

```ini
[Unit]
Description=YesWiki background jobs for mysite.com
After=network.target mysql.service

[Service]
# The user that owns the wiki's files, i.e. the one the web server runs as
User=www-data
WorkingDirectory=/home/jdoe/www/wiki
ExecStart=/usr/bin/php /home/jdoe/www/wiki/src/commands/console messenger:consume --time-limit=3600 --memory-limit=128M
Restart=always
RestartSec=5
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
```

then `systemctl enable --now yeswiki-queue`. With the `pcntl` PHP extension, SIGTERM lets the
message in hand finish before the process exits. Run one unit per wiki. `/admin/jobs` lists the consumers that ran and raises an alert
if one restarts in a loop.

The consumer stops by itself, between two messages, once the wiki's configuration changed (a
setting saved, `private/.env` edited) or its compiled container was emptied (`migrate` after an
upgrade, `cache:clear`), for its unit to start one on what is there now: keep `Restart=always`.
After an upgrade, `systemctl restart yeswiki-queue` makes that immediate.

### `request-spawn`

The end of a page view starts `messenger:drain` in a process of its own, so the visitor never
waits, at most once a minute. It needs PHP to be allowed `proc_open`, which many shared hosts
forbid. When it cannot start a process the request consumes the queue itself, as `request` would,
and `/admin/jobs` says so.

### `binary`

Under the YesWiki binary (`yeswiki serve`, a farm included) nothing is needed: the binary starts
`messenger:consume --supervised` for every wiki it serves, relays its output to its own log
prefixed with the wiki's name, and restarts it with a growing delay (up to a minute) if it keeps
exiting. A farm reloaded with SIGHUP starts and stops consumers with its list of wikis. A wiki that
chose another mode through `YESWIKI_QUEUE_CONSUMER` is left alone until the next reload.

## Installation through Docker

Instructions can be found [here](./docker/README.md)
