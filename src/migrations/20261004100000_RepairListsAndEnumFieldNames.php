<?php

use YesWiki\Core\YesWikiMigration;

require_once __DIR__ . '/20240502083251_RefactorListStruture.php';
require_once __DIR__ . '/20240621202127_RefactorEnumFieldPropertyName.php';

/** Redoes the list and enum field conversions that the earlier migrations could skip: read-protected lists, and enum fields whose entries keep their values under the full property name. */
class RepairListsAndEnumFieldNames extends YesWikiMigration
{
    public function run()
    {
        foreach ([new RefactorListStruture(), new RefactorEnumFieldPropertyName()] as $migration) {
            $migration->setServices($this->services);
            $migration->setParams($this->params);
            $migration->setDbService($this->dbService);
            $migration->run();
        }
    }
}
