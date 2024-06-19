<?php

use YesWiki\Core\YesWikiMigration;

class CheckSQLTablesThenFixThem extends YesWikiMigration
{
    public function run()
    {
        foreach ([['pages', 'id', 'int(10) unsigned NOT NULL AUTO_INCREMENT'], ['links', 'id', 'int(10) unsigned NOT NULL AUTO_INCREMENT'], ['nature', 'bn_id_nature', 'int(10) UNSIGNED NOT NULL AUTO_INCREMENT'], ['referrers', 'id', 'int(10) unsigned NOT NULL AUTO_INCREMENT'], ['triples', 'id', 'int(10) unsigned NOT NULL AUTO_INCREMENT']] as $data) {
            $this->checkThenUpdateColumnAutoincrement($data[0], $data[1], $data[2]);
        }
        foreach ([['pages', 'id', ['id']], ['links', 'id', ['id']], ['nature', 'bn_id_nature', ['bn_id_nature']], ['referrers', 'id', ['id']], ['triples', 'id', ['id']], ['users', 'name', ['name']], ['acls', 'page_tag', ['page_tag', 'privilege']], ['acls', 'privilege', ['page_tag', 'privilege']]] as $data) {
            $this->checkThenUpdateColumnPrimary($data[0], $data[1], $data[2]);
        }
    }

    private function checkThenUpdateColumnAutoincrement(
        string $tableName,
        string $columnName,
        string $SQL_columnDef
    ) {
        try {
            $data = $this->getColumnInfo($tableName, $columnName);
        } catch (Exception $ex) {
            if ($ex->getCode() != 1) {
                throw $ex;
            }
            $data = [];
        }
        if (empty($data['Extra']) || (is_string($data['Extra']) && strstr($data['Extra'], 'auto_increment') === false)) {
            if (empty($data)) {
                $dataIndex = $this->getColumnInfo($tableName, 'index');
                if (
                    !empty(array_filter($dataIndex, function ($keyData) {
                        return !empty($keyData['Key_name']) && $keyData['Key_name'] == 'PRIMARY';
                    }))
                ) {
                    $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable($tableName)} DROP PRIMARY KEY;");
                }
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable($tableName)} ADD COLUMN `$columnName` $SQL_columnDef FIRST, ADD PRIMARY KEY(`$columnName`);");
            }
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable($tableName)} MODIFY COLUMN `$columnName` $SQL_columnDef;");
            $data = $this->getColumnInfo($tableName, $columnName);
            if (empty($data['Extra']) || (is_string($data['Extra']) && strstr($data['Extra'], 'auto_increment') === false)) {
                throw new Exception("tables `$tableName`, column `$columnName` not updated !", 1);
            }
        }
    }

    private function checkThenUpdateColumnPrimary(string $tableName, string $columnName, array $newKeys)
    {
        $data = $this->getColumnInfo($tableName, $columnName);
        if (empty($data['Key']) || $data['Key'] !== 'PRI') {
            $newKeysFormatted = implode(
                ',',
                array_map(
                    function ($key) {
                        return "`{$this->dbService->escape($key)}`";
                    },
                    array_filter($newKeys)
                )
            );
            if (!empty($newKeysFormatted)) {
                $data = $this->getColumnInfo($tableName, 'index');
                if (
                    !empty(array_filter($data, function ($keyData) {
                        return !empty($keyData['Key_name']) && $keyData['Key_name'] == 'PRIMARY';
                    }))
                ) {
                    $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable($tableName)} DROP PRIMARY KEY;");
                }
                $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable($tableName)} ADD PRIMARY KEY($newKeysFormatted);");
            }
            $data = $this->getColumnInfo($tableName, $columnName);
            if (empty($data['Key']) || $data['Key'] !== 'PRI') {
                throw new Exception("tables `$tableName`, column `$columnName` key not updated !", 1);
            }
        }
    }

    private function getColumnInfo(string $tableName, string $columnName): array
    {
        if ($columnName == 'index') {
            $result = $this->dbService->query("SHOW INDEX FROM {$this->dbService->prefixTable($tableName)};");
            if (@mysqli_num_rows($result) === 0) {
                return [];
            }
        } else {
            $result = $this->dbService->query("SHOW COLUMNS FROM {$this->dbService->prefixTable($tableName)} LIKE '$columnName';");
            if (@mysqli_num_rows($result) === 0) {
                throw new Exception("tables `$tableName` not verified because error while getting `$columnName` column !", 1);
            }
        }
        $data = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        return $data;
    }
}
