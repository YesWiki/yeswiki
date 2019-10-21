# YesWiki installation
Not much to it (as long as it works, ahem). Unpack/upload the distribution files
into a directory that can be accessed via the web. Then go to the corresponding URL.  
A web-based installer will walk you through the rest.

#### Example:
If your website, say, http://www.mysite.com, is mapped to the directory /home/jdoe/www/,
and you place the YesWiki distribution files into /home/jdoe/www/wiki/, you should go to
http://www.mysite.com/wiki/.  

IMPORTANT: for installing or upgrading YesWiki, do NOT access any of the files contained
in the setup/ subdirectory. They're used by the web-based installer/updater, but you
should really just access the YesWiki directory itself, and it will (or at least should)
work perfectly.

Detailed instructions are available at http://yeswiki.net/wakka.php?wiki=DocumentationInstallation (in French).

## Installation through Docker

It is possible to install YesWiki in local through Docker.

First you need to install docker and docker-compose: https://docs.docker.com/install

A `docker-compose.yml` file can be found at the root of the YesWiki repository.
If you do `docker-compose up`, 3 Docker containers will be launched:

- yeswiki: Apache/PHP server with the YesWiki code
- db: the MySQL database
- myadmin: phpMyAdmin to see/modify the database

Then go to http://localhost. In the setup, you will need to use these informations for the MySQL serveur:

- Host ("Machine MySQL"): db
- Port: 3306
- Login: root
- Password: root

You can see/modify the created tables by going to: http://localhost:8080
