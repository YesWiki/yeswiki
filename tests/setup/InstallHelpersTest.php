<?php

namespace YesWiki\Test\Setup;

use PHPUnit\Framework\TestCase;

require_once 'includes/services/ArchiveFilename.php';
require_once 'includes/entities/DumpRewrite.php';
require_once 'includes/services/DumpRewriter.php';
require_once 'setup/install.helpers.php';

class InstallHelpersTest extends TestCase
{
    private string $folder = '';

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/yeswiki_backups_' . bin2hex(random_bytes(6));
        mkdir($this->folder, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->folder}/*") ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->folder)) {
            rmdir($this->folder);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function config(): array
    {
        return ['archive' => ['privatePath' => $this->folder]];
    }

    private function write(string $name, string $content = 'x'): void
    {
        file_put_contents("{$this->folder}/$name", $content);
    }

    public function testAFolderWithoutBackupsOffersNothing()
    {
        $this->assertSame([], availableBackups($this->config()));
    }

    public function testArchivesAreOfferedMostRecentFirst()
    {
        $this->write('2026-07-05T23-13-07_archive_only_files.zip');
        $this->write('2026-08-20T14-35-23_mydomain-ext_archive.zip');
        $this->write('not-a-backup.zip');

        $backups = availableBackups($this->config());

        $this->assertSame(
            ['2026-08-20T14-35-23_mydomain-ext_archive.zip', '2026-07-05T23-13-07_archive_only_files.zip'],
            array_column($backups, 'filename')
        );
        $this->assertSame('mydomain-ext', $backups[0]['source']);
        $this->assertSame('full', $backups[0]['type']);
        $this->assertSame('only_files', $backups[1]['type']);
    }

    public function testADumpLeftInTheFolderIsOfferedFirst()
    {
        $this->write('2026-08-20T14-35-23_mydomain-ext_archive.zip');
        $this->write('content.sql', "CREATE TABLE `yeswiki_pages` (`tag` varchar(191));\n");

        $backups = availableBackups($this->config());

        $this->assertSame('content.sql', $backups[0]['filename']);
        $this->assertSame('only_db', $backups[0]['type'], 'a dump on its own holds no file');
        $this->assertCount(2, $backups);
    }

    public function testADumpTakesTheAddressOfTheRestoreFileBesideIt()
    {
        $this->write('content.sql', "CREATE TABLE `yeswiki_pages` (`tag` varchar(191));\n");
        $this->write('restore.json', json_encode(['base_url' => 'https://mydomain.ext/subfolder/?', 'table_prefix' => 'yeswiki_']));

        $backups = availableBackups($this->config());

        $this->assertSame('mydomain-ext-subfolder', $backups[0]['source']);
        $this->assertSame('yeswiki_', rawDumpInfo($this->folder)['table_prefix']);
    }

    public function testADumpWithoutARestoreFileSaysNothingOfItsSource()
    {
        $this->write('content.sql', "CREATE TABLE `yeswiki_pages` (`tag` varchar(191));\n");

        $this->assertSame('', availableBackups($this->config())[0]['source']);
        $this->assertSame([], rawDumpInfo($this->folder));
    }

    public function testTheBackupsFolderFallsBackToTheUsualPlace()
    {
        $this->assertSame('private/backups', backupsFolder([]));
        $this->assertSame('private/backups', backupsFolder(['archive' => ['privatePath' => '%TMP']]));
        $this->assertSame('/somewhere/backups', backupsFolder(['archive' => ['privatePath' => '/somewhere/backups/']]));
    }
}
