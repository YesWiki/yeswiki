<?php

namespace YesWiki\Test\Admin;

use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A SQLite wiki's archive carries its data in the SQL dump, never as the live database file. */
class ArchiveLeavesTheSqliteDatabaseOutTest extends YesWikiTestCase
{
    public function testTheLiveDatabaseFileIsNotArchived(): void
    {
        $services = $this->getWiki()->services;
        if ($services->get(DbService::class)->getDriver() !== 'sqlite') {
            $this->markTestSkipped('only a SQLite wiki keeps its database in a file of the wiki');
        }
        $database = (string)$this->getWiki()->config['db_database'];
        $this->assertFileExists(YESWIKI_INSTANCE_DIR . '/' . $database);
        $archives = $services->get(ArchiveService::class);
        $zipPath = sys_get_temp_dir() . '/yw-sqlite-archive-' . bin2hex(random_bytes(4)) . '.zip';
        $output = '';

        try {
            $created = (new \ReflectionMethod($archives, 'createZip'))->invokeArgs($archives, [$zipPath, [], [], &$output, '', false, null, '', '', ['private']]);
            $this->assertTrue($created, $output);
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($zipPath));
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = (string)$zip->getNameIndex($i);
            }
            $zip->close();

            $this->assertContains('private/.htaccess', $names, 'the folder holding the database is archived');
            $this->assertNotContains($database, $names);
            $this->assertNotContains("$database-wal", $names);
        } finally {
            @unlink($zipPath);
        }
    }
}
