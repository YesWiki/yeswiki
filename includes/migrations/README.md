# Migrations

They are run after the wiki gets upgraded, or after an extension is installed

A migration is only run once

You can run them manually with `./yeswicli migrate`

## Create a new migration

`./yeswicli generate:migration YourMigrationName`

if it's for a specific tool/extension, for example bazar

`./yeswicli generate:migration YourMigrationName --tool=bazar`
