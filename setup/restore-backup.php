<?php

use YesWiki\Core\Service\BaseUrlRewriter;

if (empty($_POST['config']) || empty($_POST['backup_file'])) {
    header('Location: ' . myLocation());
    exit(_t('PROBLEM_WHILE_INSTALLING'));
}

echo '<h2>' . _t('INSTALL_RESTORE_FROM_BACKUP') . '</h2>';

$config = array_merge($wakkaConfig, $_POST['config']);
$config['wikini_version'] = WIKINI_VERSION;
$config['wakka_version'] = WAKKA_VERSION;
$config['yeswiki_version'] = YESWIKI_VERSION;
$config['yeswiki_release'] = YESWIKI_RELEASE;

$backupFile = basename($_POST['backup_file']); // sanitise: keep filename only
$restoreFiles = !empty($_POST['restore_files']);
$rewriteUrls = !isset($_POST['rewrite_urls']) || !empty($_POST['rewrite_urls']);

// --- connect to DB ---
mysqli_report(MYSQLI_REPORT_OFF);

test(_t('TEST_MYSQL_CONNECTION') . ' ...', $dblink = @mysqli_connect($config['mysql_host'], $config['mysql_user'], $config['mysql_password']));

$testdb = test(
    _t('SEARCH_FOR_DATABASE') . ' ...',
    @mysqli_select_db($dblink, $config['mysql_database']),
    _t('NO_DATABASE_FOUND_TRY_TO_CREATE') . '.',
    0
);
if ($testdb == 1) {
    test(
        _t('TRYING_TO_CREATE_DATABASE') . ' ...',
        @mysqli_query($dblink, 'CREATE DATABASE ' . $config['mysql_database']),
        _t('DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY') . ' !'
    );
    test(
        _t('SEARCH_FOR_DATABASE') . ' ...',
        @mysqli_select_db($dblink, $config['mysql_database']),
        _t('DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT') . ' !',
        1
    );
}

mysqli_set_charset($dblink, 'utf8mb4');
mysqli_query($dblink, 'SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');

// --- open backup zip ---
$zipPath = 'private/backups/' . $backupFile;
test(
    _t('INSTALL_RESTORE_BACKUP_FILE') . ' ...',
    substr($backupFile, -4) === '.zip' && is_file($zipPath),
    _t('INSTALL_RESTORE_ZIP_NOT_FOUND'),
    1
);

$zip = new ZipArchive();
test(
    _t('INSTALL_RESTORE_FROM_BACKUP') . ' ...',
    $zip->open($zipPath) === true,
    _t('INSTALL_RESTORE_ZIP_CANNOT_OPEN'),
    1
);

// --- restore database ---
$onlyFiles = str_ends_with($backupFile, '_archive_only_files.zip');
if (!$onlyFiles) {
    echo '<br /><b>' . _t('INSTALL_RESTORE_DB') . "</b><br>\n";

    $sqlContent = $zip->getFromName('private/backups/content.sql');
    test(
        _t('INSTALL_RESTORE_SQL_NOT_FOUND') . ' ...',
        $sqlContent !== false,
        _t('INSTALL_RESTORE_SQL_NOT_FOUND'),
        1
    );

    $info = json_decode((string)$zip->getFromName('private/backups/' . BaseUrlRewriter::INFO_FILENAME), true);
    $sourcePrefix = is_array($info) ? ($info['table_prefix'] ?? '') : '';
    test(
        _t('INSTALL_RESTORE_TABLE_PREFIX') . ' ...',
        !is_string($sourcePrefix) || $sourcePrefix === '' || $sourcePrefix === $config['table_prefix'],
        _t('INSTALL_RESTORE_TABLE_PREFIX_MISMATCH') . " : '$sourcePrefix' / '{$config['table_prefix']}'",
        1
    );

    // drop all tables with the configured prefix before importing
    $tablesPrefix = $config['table_prefix'];
    if (!empty($tablesPrefix) && $tables = mysqli_query($dblink, 'show tables')) {
        while ($row = mysqli_fetch_array($tables)) {
            $tableName = $row[0];
            if (strpos($tableName, $tablesPrefix) === 0) {
                mysqli_query($dblink, 'DROP TABLE IF EXISTS `' . $tableName . '`');
            }
        }
    }

    if ($rewriteUrls) {
        $substitutions = BaseUrlRewriter::substitutions(
            is_array($info) ? ($info['base_url'] ?? '') : '',
            $config['base_url']
        );
        if (!empty($substitutions)) {
            echo _t('INSTALL_RESTORE_REWRITE_URLS') . ' ' . htmlspecialchars(array_key_first($substitutions))
                . ' &rarr; ' . htmlspecialchars(reset($substitutions)) . "<br>\n";
            $sqlContent = BaseUrlRewriter::rewrite($sqlContent, $substitutions);
        }
    }

    $ok = mysqli_multi_query($dblink, $sqlContent);
    do {
        if ($result = mysqli_store_result($dblink)) {
            mysqli_free_result($result);
        }
    } while (mysqli_more_results($dblink) && mysqli_next_result($dblink));

    $errno = mysqli_errno($dblink);
    test(
        _t('INSTALL_RESTORE_DB') . ' ...',
        $ok && $errno === 0,
        _t('INSTALL_RESTORE_ERROR') . ' (errno ' . $errno . '): ' . mysqli_error($dblink),
        1
    );
}

// --- restore files ---
$onlyDb = str_ends_with($backupFile, '_archive_only_db.zip');
if ($restoreFiles && !$onlyDb) {
    echo '<br /><b>' . _t('INSTALL_RESTORE_DB_AND_FILES') . "</b><br>\n";
    $wikiRoot = realpath(getcwd());
    $skipPrefix = 'private/backups/';
    $skipFile = 'wakka.config.php';
    $errors = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (strpos($name, $skipPrefix) === 0 || $name === $skipFile || strpos($name, '..') !== false) {
            continue;
        }
        if (str_ends_with($name, '/')) {
            $dir = $wikiRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                $errors[] = $name;
            }
            continue;
        }
        if (!$zip->extractTo($wikiRoot, $name)) {
            $errors[] = $name;
        }
    }

    test(
        _t('INSTALL_RESTORE_DB_AND_FILES') . ' ...',
        empty($errors),
        _t('INSTALL_RESTORE_ERROR') . ': ' . implode(', ', array_slice($errors, 0, 5)),
        0
    );
}

$zip->close();

// --- write config ---
echo '<br />';
$_POST['config'] = json_encode($config);
require_once 'setup/writeconfig.php';
