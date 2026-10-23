<?php

use YesWiki\Core\YesWikiMigration;

/**
 * Makes ActivityPub templates write entry dates as ISO 8601 with an offset, which remote servers require.
 */
class GiveActivityPubDatesATimezone extends YesWikiMigration
{
    private const REPLACEMENTS = [
        'date_creation_fiche | replace({" ": "T"})' => 'date_creation_fiche | date("c")',
        'date_maj_fiche | replace({" ": "T"})' => 'date_maj_fiche | default(date_creation_fiche) | date("c")',
    ];

    public function run()
    {
        $table = $this->dbService->prefixTable('nature');

        foreach ($this->dbService->loadAll("SELECT `bn_id_nature`, `bn_sem_template` FROM {$table}") as $form) {
            $template = (string)$form['bn_sem_template'];
            $fixed = strtr($template, self::REPLACEMENTS);
            if ($fixed !== $template) {
                $this->dbService->query("UPDATE {$table} SET `bn_sem_template` = '" . $this->dbService->escape($fixed) . "' WHERE `bn_id_nature` = '" . $this->dbService->escape($form['bn_id_nature']) . "'");
            }
        }
    }
}
