<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Kernel\Command\DbCommand;
use YesWiki\Kernel\Database\DumpRewriter;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** core:exportdb hands mysqldump this wiki's tables, every one of them and no other wiki's. */
class DbCommandExportsOwnTablesTest extends YesWikiTestCase
{
    public function testTheDumpCreatesExactlyTheWikisOwnTables(): void
    {
        $db = $this->getWiki()->services->get(DbService::class);
        if ($db->getDriver() !== 'mysql') {
            $this->markTestSkipped('core:exportdb runs mysqldump, which only a MySQL wiki uses');
        }
        $file = sys_get_temp_dir() . '/yw-exportdb-' . bin2hex(random_bytes(4)) . '.sql';

        try {
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(YESWIKI_PROGRAM_DIR . '/src/commands/console') . ' core:exportdb --filepath=' . escapeshellarg($file) . ' 2>&1', $out, $status);
            if (!is_file($file) || filesize($file) === 0) {
                $this->markTestSkipped('mysqldump is not usable here: ' . implode("\n", $out));
            }

            $created = DumpRewriter::tables((string)file_get_contents($file));
            $own = DumpRewriter::ownTables($db->schema()->getTables(), trim($db->prefixTable('')));
            sort($created);
            sort($own);

            $this->assertSame($own, $created);
        } finally {
            @unlink($file);
        }
    }

    public function testTheVersionLineOfEitherToolIsRecognised(): void
    {
        foreach ([
            '/run/current-system/sw/bin/mysqldump from 11.4.12-MariaDB, client 10.19 for Linux (x86_64)',
            'mariadb-dump from 11.4.12-MariaDB, client 10.19 for Linux (x86_64)',
            'mysqldump  Ver 10.19 Distrib 10.6.12-MariaDB, for debian-linux-gnu (x86_64)',
            'mysqldump  Ver 8.4.2 for Linux on x86_64 (MySQL Community Server - GPL)',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe  Ver 8.0.36 for Win64 on x86_64',
        ] as $line) {
            $this->assertTrue(DbCommand::isDumpToolVersion($line), $line);
        }
        $this->assertFalse(DbCommand::isDumpToolVersion('mysql  Ver 15.1 Distrib 10.6.12-MariaDB'));
        $this->assertFalse(DbCommand::isDumpToolVersion(''));
    }

    public function testAMariaDbDumpIsADumpEvenBehindItsSandboxLine(): void
    {
        $this->assertTrue(DbCommand::isDump("/*M!999999\\- enable the sandbox mode */\n-- MariaDB dump 10.19-11.4.12-MariaDB, for Linux (x86_64)\n"));
        $this->assertTrue(DbCommand::isDump("-- MySQL dump 10.13  Distrib 8.4.2, for Linux (x86_64)\n"));
        $this->assertFalse(DbCommand::isDump("mysqldump: Got error: 1045: Access denied\n"));
    }

    public function testAnArchiveTakesTheDumpToolsDumpWhenTheToolWorks(): void
    {
        $services = $this->getWiki()->services;
        if ($services->get(DbService::class)->getDriver() !== 'mysql') {
            $this->markTestSkipped('only a MySQL wiki dumps through mysqldump');
        }
        $archives = $services->get(ArchiveService::class);
        if (!(new \ReflectionMethod($archives, 'testDb'))->invoke($archives)) {
            $this->markTestSkipped('core:exportdb --test does not answer OK here');
        }
        $file = sys_get_temp_dir() . '/yw-archive-dump-' . bin2hex(random_bytes(4)) . '.sql';

        try {
            (new \ReflectionMethod($archives, 'dumpDatabaseInto'))->invoke($archives, $file);

            $this->assertTrue(DbCommand::isDump((string)file_get_contents($file, false, null, 0, 4096)), 'the archive kept the dump tool\'s dump rather than falling back to SqlDumper');
        } finally {
            @unlink($file);
        }
    }
}
