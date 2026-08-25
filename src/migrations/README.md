# Migrations

They are run after the wiki gets upgraded, or after an extension is installed

A migration is only run once

You can run them manually with `./yeswicli migrate`

## Create a new migration

`./yeswicli generate:migration YourMigrationName`

if it's for a specific tool/extension, for example bazar

`./yeswicli generate:migration YourMigrationName --tool=bazar`

## Saying what happened

A migration talks to the operator through `$this->say('...')`, which `yeswicli migrate` prints and
the web upgrade screen shows. `MigrationService` records one Journal entry per migration --
`migration.applied`, with the migration's name -- and a migration writes nothing else to it
(ticket 53).

**A finding does not go in a `say()`.** "Your themes still call `{{searchform}}`" is a claim about
the _present_: it stops being true the moment somebody edits the file, and a line written once
would still be saying it a year later. Declare it as a Health check in the module that owns the
subject (`ProvidesHealthChecks`) and run it here with `$this->reportCheck('its-id')`. The operator
at the terminal hears it now, and `/admin/health` keeps answering it afterwards -- until it stops
being true.
