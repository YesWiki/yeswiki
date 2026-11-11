<?php

/* The one migration run.sh adds to the image it rolls out under writes: it only adds a row, as a migration must on replicas. */

use YesWiki\Core\YesWikiMigration;

class KindRollingUpdate extends YesWikiMigration
{
    public function run()
    {
        $this->dbService->query('INSERT INTO' . $this->dbService->prefixTable('triples') . '(resource, property, value) VALUES (?, ?, ?)', ['kind_rollout', 'applied', '1']);
    }
}
