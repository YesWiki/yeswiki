# Docker usage

This directory contain 2 dockerfiles and 2 docker-compose. One of each for dev and the other for production.

## Dev

The dev docker-compose contain the following images :

- yeswiki-app: This image have only a php-fpm process and mount the directory in the container to be able to develop locally
- yeswiki-db : a mysql (mariadb seems to not work properly currently) accessible from yeswiki with domaine name `yeswiki-db`
- yeswiki-web : a nginx reverse-proxy. configuration can be found on nginx.conf file. Accessible on `localhost:8085`
- myadmin : phpmyadmin accessible on `localhost:8086`
- mail : container to intercept email send by yeswiki. Webmail is accessible on `localhost:1080`.You have to set the following in `wakka.config.php`

```
'contact_mail_func' => 'smtp',
'contact_smtp_host' => 'mail',
'contact_smtp_port' => '1025',
```

### How-To

> [!]info
> all commands have to be launched from docker directory

To be able to develop locally without messing up with users and permissions, the dev dockerfile uses the same user and group as computer user.
You need to create a file called `.env` within the `docker` directory with the following content :

```
UID="YOUR_USER_ID" # can be found with id -u
GID="YOUR_USER_GID" # can be found with id -g
```

Then you can build the container with the following command :

```
docker compose build
```

Once done, you can start containers :

```
docker compose up
# or docker compose up -d if you want to detach from terminal
```

It should take some time for the first launch, it will perform `compose install` and `yarn install`.
Then yeswiki will be accessible at [localhost:8085](http://localhost:8085),
phpmyadmin at [localohost:8086](http://localhost:8086) and mailcatcher at [localhost:1080](http://localhost:1080).

Once on the install page, use the following values :

- **Mysql server host** : yeswiki-db
- **MYSQL database name** : yeswiki (can be found in yeswiki.secret)
- **MYSQL username** : yeswiki (can be found in yeswiki.secret)
- **MYSQL password** : password (can be found in yeswiki.secret)

> [!]tips
> if you have a previous developpement installation you may need to change value accordingly in the wakka.config.php

## reinitialize yeswiki repo from dev

docker create and populates the following folders files :

- vendor (for php dependencies)
- node_modules (for yarn dependencies)
- wakka.config.php
- cache
- tools/bazar/vendor/

It should be enough to remove the `wakka.config.php` file

## Remove database

- remove containers (stopping container doesn't remove them)

```
docker compose down
```

- remove docker volume (containing database files)

```
docker volume rm yeswiki-db
```

## updating php or yarn dependency

You can simply restart container with the following command :

```
docker compose restart
```

If you want to update php or yarn dependency without restarting everything, you can do the following commands.

```
docker compose exec yeswiki-app composer install
docker compose exec yeswiki-app yarn install
```
