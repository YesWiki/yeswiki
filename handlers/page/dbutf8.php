<?php

use YesWiki\Core\Service\ConfigurationFileProvider;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Security\Controller\SecurityController;

if (isset($this)) {
    if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
        throw new Exception(_t('WIKI_IN_HIBERNATION'));
    }
    if ($this->userIsAdmin()) {
        include_once YESWIKI_SOURCE_DIR . '/includes/Encoding.php';
        $dbService = $this->services->get(\YesWiki\Core\Service\DbService::class);
        $output = '';
        $result = $this->LoadAll(
            'SHOW TABLES FROM ' . $this->config['db_database']
            . ' LIKE "' . $this->config['table_prefix'] . '%"'
        );
        $this->query('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->query('ALTER DATABASE `' . $this->config['db_database'] . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $tables = [
            $this->config['table_prefix'] . 'acls',
            $this->config['table_prefix'] . 'links',
            $this->config['table_prefix'] . 'nature',
            $this->config['table_prefix'] . 'pages',
            $this->config['table_prefix'] . 'referrers',
            $this->config['table_prefix'] . 'triples',
            $this->config['table_prefix'] . 'users',
        ];

        foreach ($tables as $table) {
            if ($table == $this->config['table_prefix'] . 'triples') {
                $query = 'ALTER TABLE `' . $this->config['table_prefix'] . 'triples` CHANGE `resource`'
                . ' `resource` VARCHAR(191), CHANGE `property` `property` VARCHAR(191);';
                $output .= '<hr>' . $query . '<br>';
                $this->query($query);
            }
            $query = 'ALTER TABLE `' . $table . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
            $output .= '<hr>' . $query . '<br>';
            $this->query($query);
            $queryConvert = 'ALTER TABLE `' . $table . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
            $output .= '<hr>' . $queryConvert . '<br>';
            $this->query($queryConvert);

            // Change Field
            if ($table == $this->config['table_prefix'] . 'pages') {
                $cols = $this->LoadAll('SHOW COLUMNS FROM ' . $table);
                $dataQuery = 'SELECT * FROM ' . $table;
                $data = $this->LoadAll($dataQuery);
                foreach ($cols as $row) {
                    if ($row['Type'] == 'mediumtext' or $row['Type'] == 'text' or $row['Type'] == 'longtext' or $row['Type'] == 'blob') {
                        // Printing results in HTML
                        foreach ($data as $line) {
                            // Convert TO String
                            $transform = $line[$row['Field']];
                            if (@iconv('utf-8', 'utf-8//IGNORE', $transform) != $transform) {
                                $transform = ForceUTF8\Encoding::toUTF8($transform);
                            }
                            $transform = \ForceUTF8\Encoding::fixUTF8($transform);
                            $transform = $dbService->escape($transform);
                            $updateQuery = 'UPDATE ' . $table . ' SET `' . $row['Field'] . '` = "' . $transform . '" WHERE `id`="' . $line['id'] . '";';
                            $output .= '<hr>' . $updateQuery . '<br>';
                            $this->query($updateQuery);
                        }
                    }
                }
            }
            if (false and $table == $this->config['table_prefix'] . 'nature') {
                $cols = $this->LoadAll('SHOW COLUMNS FROM ' . $table);
                $dataQuery = 'SELECT * FROM ' . $table . ';';
                $data = $this->LoadAll($dataQuery);
                foreach ($cols as $row) {
                    if (strstr($row['Type'], 'varchar') or $row['Type'] == 'mediumtext' or $row['Type'] == 'text' or $row['Type'] == 'longtext' or $row['Type'] == 'blob') {
                        // Printing results in HTML
                        foreach ($data as $line) {
                            // Convert TO String
                            $transform = $line[$row['Field']];
                            if (@iconv('utf-8', 'utf-8//IGNORE', $transform) != $transform) {
                                $transform = ForceUTF8\Encoding::toUTF8($transform);
                            }
                            $transform = \ForceUTF8\Encoding::fixUTF8($transform);
                            $transform = $dbService->escape($transform);
                            $updateQuery = 'UPDATE ' . $table . ' SET `' . $row['Field'] . '` = "' . $transform . '" WHERE `bn_id_nature`="' . $line['bn_id_nature'] . '";';
                            $output .= '<hr>' . $updateQuery . '<br>';
                            $this->query($updateQuery);
                        }
                    }
                }
            }
            $this->query('ALTER TABLE ' . $table . ' ENGINE=InnoDB;');
            $output .= 'Complete Table: <b>' . $table . '</b><br>';
        }

        $output .= '<h3>Complete ALL</h3>';

        // ajout du charset utf8mb4 dans wakka.config.php
        $config = $this->services->get(ConfigurationService::class)->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        $config->db_charset = 'utf8mb4';
        $config->write();

        // affichage a l'ecran
        echo $this->header()
        . '<div class="alert alert-success">'
        . _t('handler dbutf8 : toutes les tables de la base de données ont été transformées en utf8.')
        . '</div>'
        . $output
        . $this->footer();
    } else {
        echo $this->header()
        . '<div class="alert alert-danger">' . _t('handler dbutf8 : réservé aux administrateurs.') . '</div>'
        . $this->footer();
    }
}

if (php_sapi_name() === 'cli') {
    $cwd = dirname(exec('pwd'), 2);
    $cwd = str_replace(DIRECTORY_SEPARATOR . 'git', '', $cwd);
    set_include_path($cwd);
    include_once ConfigurationFileProvider::getConfigFileFromEnv();
    include_once YESWIKI_SOURCE_DIR . '/includes/Encoding.php';

    $charset = isset($wakkaConfig['db_charset']) ? $wakkaConfig['db_charset'] : 'utf8mb4';
    $dsn = 'mysql:host=' . $wakkaConfig['db_host'] . ';dbname=' . $wakkaConfig['db_database'] . ';charset=' . $charset;
    if (isset($wakkaConfig['db_port']) && $wakkaConfig['db_port']) {
        $dsn .= ';port=' . $wakkaConfig['db_port'];
    }

    try {
        $GLOBALS['dblink'] = new \PDO(
            $dsn,
            $wakkaConfig['db_user'],
            $wakkaConfig['db_password'],
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );
        if ($charset === 'utf8mb4') {
            $GLOBALS['dblink']->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
    } catch (\PDOException $e) {
        exit('Database connection failed: ' . $e->getMessage());
    }

    function sqlQuery($query)
    {
        $result = $GLOBALS['dblink']->query($query);
        if ($result === false) {
            $errorInfo = $GLOBALS['dblink']->errorInfo();
            exit('Query failed: ' . $query . ' (' . $errorInfo[2] . ')');
        }
        return $result;
    }

    function LoadAll($query)
    {
        $stmt = sqlQuery($query);
        return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    function escapeString($string)
    {
        $quoted = $GLOBALS['dblink']->quote($string);
        return substr($quoted, 1, -1);
    }

    $result = LoadAll(
        'SHOW TABLES FROM ' . $wakkaConfig['db_database']
        . ' LIKE "' . $wakkaConfig['table_prefix'] . '%"'
    );
    sqlQuery('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    sqlQuery('ALTER DATABASE `' . $wakkaConfig['db_database'] . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $tables = [
        $wakkaConfig['table_prefix'] . 'acls',
        $wakkaConfig['table_prefix'] . 'links',
        $wakkaConfig['table_prefix'] . 'nature',
        $wakkaConfig['table_prefix'] . 'pages',
        $wakkaConfig['table_prefix'] . 'referrers',
        $wakkaConfig['table_prefix'] . 'triples',
        $wakkaConfig['table_prefix'] . 'users',
    ];

    foreach ($tables as $table) {
        if ($table == $wakkaConfig['table_prefix'] . 'triples') {
            $query = 'ALTER TABLE `' . $wakkaConfig['table_prefix'] . 'triples` CHANGE `resource`'
              . ' `resource` VARCHAR(191), CHANGE `property` `property` VARCHAR(191);';
            echo '<hr>' . $query . '<br>';
            sqlQuery($query);
        }
        $query = 'ALTER TABLE `' . $table . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
        echo '<hr>' . $query . '<br>';
        sqlQuery($query);
        $queryConvert = 'ALTER TABLE `' . $table . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
        echo '<hr>' . $queryConvert . '<br>';
        sqlQuery($queryConvert);

        // Change Field
        if ($table == $wakkaConfig['table_prefix'] . 'pages') {
            $cols = loadAll('SHOW COLUMNS FROM ' . $table);
            $dataQuery = 'SELECT * FROM ' . $table;
            $data = loadAll($dataQuery);
            foreach ($cols as $row) {
                if ($row['Type'] == 'mediumtext' or $row['Type'] == 'text' or $row['Type'] == 'longtext' or $row['Type'] == 'blob') {
                    // Printing results in HTML
                    foreach ($data as $line) {
                        // Convert TO String
                        $transform = $line[$row['Field']];
                        if (@iconv('utf-8', 'utf-8//IGNORE', $transform) != $transform) {
                            $transform = ForceUTF8\Encoding::toUTF8($transform);
                        }
                        $transform = \ForceUTF8\Encoding::fixUTF8($transform);
                        $transform = escapeString($transform);
                        $updateQuery = 'UPDATE ' . $table . ' SET `' . $row['Field'] . '` = "' . $transform . '" WHERE `id`="' . $line['id'] . '";';
                        echo '<hr>' . $updateQuery . '<br>';
                        sqlQuery($updateQuery);
                    }
                }
            }
        }
        if (false and $table == $wakkaConfig['table_prefix'] . 'nature') {
            $cols = loadAll('SHOW COLUMNS FROM ' . $table);
            $dataQuery = 'SELECT * FROM ' . $table . ';';
            $data = loadAll($dataQuery);
            foreach ($cols as $row) {
                if (strstr($row['Type'], 'varchar') or $row['Type'] == 'mediumtext' or $row['Type'] == 'text' or $row['Type'] == 'longtext' or $row['Type'] == 'blob') {
                    // Printing results in HTML
                    foreach ($data as $line) {
                        // Convert TO String
                        $transform = $line[$row['Field']];
                        if (@iconv('utf-8', 'utf-8//IGNORE', $transform) != $transform) {
                            $transform = ForceUTF8\Encoding::toUTF8($transform);
                        }
                        $transform = \ForceUTF8\Encoding::fixUTF8($transform);
                        $transform = escapeString($transform);
                        $updateQuery = 'UPDATE ' . $table . ' SET `' . $row['Field'] . '` = "' . $transform . '" WHERE `bn_id_nature`="' . $line['bn_id_nature'] . '";';
                        echo '<hr>' . $updateQuery . '<br>';
                        sqlQuery($updateQuery);
                    }
                }
            }
        }
        sqlQuery('ALTER TABLE ' . $table . ' ENGINE=InnoDB;');
        echo 'Complete Table: <b>' . $table . '</b><br>';
    }

    // ajout du charset utf8mb4 dans wakka.config.php
    $config = $this->services->get(ConfigurationService::class)->getConfiguration($cwd . '/wakka.config.php');
    $config->load();
    $config->db_charset = 'utf8mb4';
    $config->write();

    echo '<h3>Complete ALL</h3>';
}
