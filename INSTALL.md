# YesWiki installation

Not much to it (as long as it works, ahem). Unpack/upload the distribution files
into a directory that can be accessed via the web. Then go to the corresponding URL.
A web-based installer will walk you through the rest.

**Requirements**: PHP >= 8.3, and a MySQL/MariaDB, PostgreSQL or SQLite database.

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
xml
xmlreader
zip
```

**A database driver** — at least one, matching the database you are pointing the wiki at. The
installer only offers a driver whose extension is loaded:

| extension | database |
| --- | --- |
| `pdo_mysql` | MySQL / MariaDB |
| `pdo_pgsql` | PostgreSQL |
| `pdo_sqlite` | SQLite — no server to set up, good for a small wiki |

**Optional** — core checks for each at run time and does without, at a cost:

| extension | what it buys you |
| --- | --- |
| `imap` | the IMAP importer can read a mailbox (with `php-imap/php-imap`) |
| `intl` | search folds accents across more scripts; without it, an `iconv` transliteration covers fewer |
| `zend-opcache` | saving a setting takes effect at once instead of when the opcode cache next revalidates |

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

## Installation through Docker

Instructions can be found [here](./docker/README.md)
