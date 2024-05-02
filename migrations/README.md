# Migrations

They are run after the wiki gets upgraded, or after an extension is installed

A migration is only run once

## Create a new migration

Create a file in the `migrations` folder (can be created also inside a tool, i.e. `tools/bazar/migrations`)

Choose a name for the migration (for example `TestMigration`) and prefix the file name with current date, i.e. `2024_04_25_TestMigration.php`. The date is there to ensure uniq migration name

```
// migrations/2024-04_25_TestMigration.php

<?php
use YesWiki\Core\YesWikiMigration;

class TestMigration extends YesWikiMigration
{
    public function run()
    {
        // your code goes here
        // $this->dbService is available
        // $this->wiki is also available, so you can get other services if needed
    }
}
```