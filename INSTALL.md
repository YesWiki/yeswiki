# YesWiki installation

Not much to it (as long as it works, ahem). Unpack/upload the distribution files
into a directory that can be accessed via the web. Then go to the corresponding URL.
A web-based installer will walk you through the rest.

**Important**:
If checked out from git repository, you need to install deps via `composer`.  
So after downloading/synchroning files on your server, run `composer install`.  
You can find information about installation of `composer` [here](https://getcomposer.org).

## Web installer example

If your site <https://mysite.com> is mapped to the directory `/home/jdoe/www`,  
and you place the YesWiki files into `/home/jdoe/www/wiki`, you should go to  
<https://mysite.com/wiki>.

**Important:**  
For installing or upgrading YesWiki, do NOT access any of the files contained  
in the `setup/` folder, you should just access the YesWiki root folder.

Detailed instructions are available [in the official doc](https://yeswiki.net/?doc#/docs/fr/webmaster?id=installation).

## Installation through Docker

First you need to install docker and docker-compose: <https://docs.docker.com/install>

Run `cd docker && docker compose up -d` to install and launch the containers

Then go to <http://localhost:8085>.
In the setup, you will need to provide following configuration for the database:

- MySQL Host: yeswiki-db
- MySql Database: yeswiki (see `docker/yeswiki.secrets`)
- MySql Username: yeswiki (see `docker/yeswiki.secrets`)
- MySql Password: password (see `docker/yeswiki.secrets``)
