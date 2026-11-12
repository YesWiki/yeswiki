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

## On a replicated wiki

With `YESWIKI_REPLICATED=1`, `migrate` runs as a Job with the new image before the rollout, while the
old replicas still serve. They keep reading, and refuse writes until they are replaced. So every
migration must work with the code of the release before it: add a column, a table or a page, never
rename or drop one that the previous release still reads. That is expand, then contract: the
migration that removes the old shape ships in a later release than the one that adds the new shape.

Two `migrate` runs started together take the `migrations` lock in turn: the second waits for the
first and then finds nothing to do.
