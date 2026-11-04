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
`YESWIKI_QUEUE_CONSUMER` says what. There are three modes:

| Mode      | What consumes the queue                                                                                       | You set up                                     |
| --------- | ------------------------------------------------------------------------------------------------------------- | ---------------------------------------------- |
| `request` | the end of each page view, once the page has been sent                                                        | nothing (the default)                          |
| `api`     | an HTTP call to `/api/queue/consume`, POST or GET (online cron, monitor)                                      | a call, and a secret                           |
| `worker`  | a process that never returns, `messenger:consume`, kept alive by the YesWiki binary, or by systemd or similar | the setting; and a service, outside the binary |

Nothing has to be chosen: a wiki that says nothing consumes in `request` mode, under the binary
too, which is what keeps a wiki that is installed and forgotten working on shared
hosting with no shell and no cron. The cost is that work only moves when someone visits, so a quiet
wiki lags, and where PHP cannot send the page before the script ends (mod_php, CGI: the PHP that runs inside Apache, or as a plain CGI program) the visitor
waits for it, within a budget of a few seconds. An online cron or a worker has neither problem.

The mode is read from the real environment, then from `private/.env` (see `.env.example`; the only
option on a host where you cannot set environment variables), then from `queue_consumer` in
`yeswiki.config.php`, and falls back to the default:

```sh
YESWIKI_QUEUE_CONSUMER=worker
```

The binary does not set this variable, it only marks the wiki as served by it, so each wiki of a
farm chooses its mode. `/admin/jobs` shows the mode in force and where the setting was
read. That page and `/admin/health` raise an alert when jobs wait and nothing consumes them, when
the value is not a mode, or when the mode needs something that is missing; each alert is explained
in [the admin documentation](docs/fr/async.md).

A pass spends what the server allows a request (`max_execution_time`, `memory_limit`) less a
margin, and only one pass runs at a time per wiki. A job that cannot finish inside that is cut and
moved to the failed jobs, with the advice to switch to `worker`, where nothing cuts it.

### Emptying the queue by hand

```sh
./yeswicli messenger:consume --drain
```

queues the scheduled tasks that are due (none ahead of its time), consumes what is waiting, then
exits: it never waits for more, and has no time limit. It exits 0 when it ended normally (or found another pass already running,
which it says), 1 on an error and 2 on a bad option. It is meant for a terminal, whatever the mode;
to consume regularly outside page views, choose `worker` or `api`.

`./yeswicli core:maintenance`, which the alphas ran from a crontab, no longer exists, nor does the
`maintenance_trigger` setting, which `./yeswicli migrate` removes: remove that crontab line, and a
`MAINTENANCE_TRIGGER` line in `private/.env`.

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

Under the YesWiki binary, see [below](#worker-under-the-yeswiki-binary): the binary keeps the
process up, and there is no service to write.

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

### `worker` under the YesWiki binary

Under the YesWiki binary (`yeswiki serve`, a farm included), a wiki consumes in `request` mode
unless it asks for `worker` with `YESWIKI_QUEUE_CONSUMER=worker` in its `private/.env`: a consumer
kept alive is a PHP process per wiki, whose memory a wiki pays only if it wants work that moves
without visits, or jobs longer than a page's time limit. There is no service to write: the binary
starts `messenger:consume --supervised` for every wiki it serves, and a wiki in another mode exits
it at once and is left alone until the next reload. For the wikis in `worker`, it relays the
consumer's output to its own log prefixed with the wiki's name, and restarts it with a growing delay
(up to a minute) if it keeps exiting. A farm reloaded with SIGHUP starts and stops consumers with its
list of wikis, and is when a wiki's change to or from `worker` takes effect. A systemd unit kept from
before the binary is not needed: remove it. Left in place it does no harm, as both consumers run and
each message is claimed by one of them only, but it is one more PHP process for the same work.

## Installation through Docker

Instructions can be found [here](./docker/README.md)
