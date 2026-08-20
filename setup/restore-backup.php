<?php

use YesWiki\Core\Service\DumpRewriter;
use YesWiki\Core\Service\SqlScript;

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
$config['table_prefix'] = trim($config['table_prefix']);

$backupFile = basename($_POST['backup_file']); // sanitise: keep filename only
$restoreFiles = !empty($_POST['restore_files']);
$rewriteUrls = !isset($_POST['rewrite_urls']) || !empty($_POST['rewrite_urls']);
$dropExisting = !empty($_POST['drop_existing']);

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
$zipPath = backupsFolder($wakkaConfig) . '/' . $backupFile;
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

    $dumpStream = $zip->getStream('private/backups/content.sql');
    test(
        _t('INSTALL_RESTORE_SQL') . ' ...',
        $dumpStream !== false,
        _t('INSTALL_RESTORE_SQL_NOT_FOUND'),
        1
    );

    $dumpTables = [];
    foreach (SqlScript::statementsFromStream($dumpStream) as $statement) {
        foreach (DumpRewriter::tables($statement) as $table) {
            $dumpTables[$table] = true;
        }
    }
    fclose($dumpStream);

    $info = json_decode((string)$zip->getFromName('private/backups/' . DumpRewriter::INFO_FILENAME), true);
    $tablesPrefix = $config['table_prefix'];
    $dump = null;
    $dumpError = '';
    try {
        $dump = DumpRewriter::plan(array_keys($dumpTables), is_array($info) ? $info : [], $tablesPrefix, $config['base_url'], $rewriteUrls);
    } catch (Exception $exception) {
        $dumpError = $exception->getCode() === DumpRewriter::UNKNOWN_SOURCE_PREFIX
            ? _t('INSTALL_RESTORE_TABLE_PREFIX_UNKNOWN')
            : _t('INSTALL_RESTORE_TABLE_PREFIX_INVALID') . " : '$tablesPrefix'";
    }
    test(_t('INSTALL_RESTORE_TABLE_PREFIX') . ' ...', $dump !== null, $dumpError, 1);

    $existingTables = [];
    if ($tables = mysqli_query($dblink, 'show tables')) {
        while ($row = mysqli_fetch_array($tables)) {
            if (strpos($row[0], $tablesPrefix) === 0) {
                $existingTables[] = $row[0];
            }
        }
    }
    test(
        _t('CHECK_EXISTING_TABLE_PREFIX') . ' ...',
        empty($existingTables) || $dropExisting,
        _t('TABLE_PREFIX_ALREADY_USED') . ' (' . implode(', ', array_slice($existingTables, 0, 5)) . ')',
        1
    );
    foreach ($existingTables as $tableName) {
        mysqli_query($dblink, 'DROP TABLE IF EXISTS `' . $tableName . '`');
    }

    if ($dump->renamedTables()) {
        echo _t('INSTALL_RESTORE_REWRITE_TABLE_PREFIX') . ' ' . htmlspecialchars($dump->prefixFrom)
            . ' &rarr; ' . htmlspecialchars($dump->prefixTo) . "<br>\n";
    }
    if ($dump->rewroteUrls()) {
        echo _t('INSTALL_RESTORE_REWRITE_URLS') . ' ' . htmlspecialchars($dump->urlFrom)
            . ' &rarr; ' . htmlspecialchars($dump->urlTo) . "<br>\n";
    }

    $importError = '';
    $dumpStream = $zip->getStream('private/backups/content.sql');
    try {
        SqlScript::runStatements($dblink, (function () use ($dumpStream, $dump) {
            foreach (SqlScript::statementsFromStream($dumpStream) as $statement) {
                yield $dump->apply($statement);
            }
        })());
    } catch (Exception $exception) {
        $importError = $exception->getMessage();
    }
    fclose($dumpStream);
    test(
        _t('INSTALL_RESTORE_DB') . ' ...',
        $importError === '',
        _t('INSTALL_RESTORE_ERROR') . ' : ' . htmlspecialchars($importError),
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
