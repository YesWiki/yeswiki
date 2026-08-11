# YesWiki installation

Not much to it (as long as it works, ahem). Unpack/upload the distribution files
into a directory that can be accessed via the web. Then go to the corresponding URL.
A web-based installer will walk you through the rest.

**Requirements**: PHP >= 8.3, and a MySQL/MariaDB, PostgreSQL or SQLite database.

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
