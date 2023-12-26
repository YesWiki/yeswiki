# Docker usage

## Build image

From within `docker` folder

 - create a file `.env` with the following value : 


```
export UID="YOUR_USER_ID" # can be found with id -u
export GID="YOUR_USER_GID" # can be found with id -g

```
 - copy the file `yeswiki.secret.example` to `yeswiki.secret`
 - build the container
```
docker compose build 
```
 - launch image
```
docker compose up -d
```

It should take some time for the first launch, it will perform `compose install` and `yarn install`.
then yeswiki will be accessible at `localhost:8085`, phpmyadmin at `localohost:8086` and mailcatcher at `localhost:1080`

Once on the install page, use the following values : 

- **Mysql server host** : yeswiki-db
- **MYSQL database name** : yeswiki (can be fond in yeswiki.secret)
- **MYSQL username** : yeswiki (can be fond in yeswiki.secret)
- **MYSQL password** : password (can be fond in yeswiki.secret)

> [!]tips
> if you have a previous developpement installation you may need to change value accordingly in the wakka.config.php

## reinitialize yeswiki repo from dev

docker create and populates the following folders files :

- vendor (for php dependencies)
- node_modules (for yarn dependencies)
- wakka.config.php
- cache
- tools/bazar/vendor/

## Remove database

- remove containers
```
docker compose down 
```
- remove docker volume
```
docker volume rm yeswiki-db
```

## updating php or yarn dependency

```
docker compose exec yeswiki-app bash
composer install
yarn install
```
