<?php

namespace YesWiki\Test\Admin;

use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A full backup is the whole wiki: what it lacks goes, in the folders it holds, and nothing the wiki keeps for itself does. */
class RestoreRemovesWhatTheBackupLacksTest extends YesWikiTestCase
{
    private string $root = '';
    private string $zipPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/yw-restore-removes-' . bin2hex(random_bytes(4));
        $this->zipPath = $this->root . '.zip';
        foreach ([
            'files/kept.jpg' => 'kept',
            'files/added-since.jpg' => 'added since',
            'files/old/deeper.txt' => 'gone',
            'custom/kept.css' => 'kept',
            'cache/archive/output-uid.log' => 'progress',
            'private/backups/2026-01-01T00-00-00_archive.zip' => 'a backup',
            'private/.env' => 'SECRET=1',
            'tools/not-in-the-backup.php' => 'left alone',
            'yeswiki.config.php' => '<?php',
        ] as $path => $content) {
            @mkdir(\dirname($this->root . '/' . $path), 0o755, true);
            file_put_contents($this->root . '/' . $path, $content);
        }

        $zip = new \ZipArchive();
        $zip->open($this->zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addEmptyDir('cache');
        $zip->addEmptyDir('files');
        $zip->addFromString('files/kept.jpg', 'kept');
        $zip->addEmptyDir('custom');
        $zip->addFromString('custom/kept.css', 'kept');
        $zip->addEmptyDir('private');
        $zip->addFromString('private/backups/content.sql', '');
        $zip->addFromString('yeswiki.config.php', '<?php');
        $zip->close();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root) . ' ' . escapeshellarg($this->zipPath));
        parent::tearDown();
    }

    public function testFilesAddedSinceTheBackupAreRemoved(): void
    {
        $zip = new \ZipArchive();
        $zip->open($this->zipPath);
        try {
            $removed = $this->getWiki()->services->get(ArchiveService::class)->removeFilesAbsentFromArchive($zip, $this->root);
        } finally {
            $zip->close();
        }

        $this->assertSame(2, $removed);
        $this->assertFileDoesNotExist($this->root . '/files/added-since.jpg');
        $this->assertDirectoryDoesNotExist($this->root . '/files/old', 'an emptied folder the backup does not hold goes too');
        $this->assertFileExists($this->root . '/files/kept.jpg');
        $this->assertFileExists($this->root . '/custom/kept.css');
    }

    public function testTheBackupsSettingsComeBackExceptThisWikisOwn(): void
    {
        $configFile = $this->root . '/restored.config.php';
        file_put_contents($configFile, "<?php\n\$yeswikiConfig = " . var_export([
            'base_url' => 'https://here.test/?',
            'db_password' => 'local-secret',
            'contact_smtp_pass' => 'local-mail',
            'yeswiki_name' => 'Before',
        ], true) . ";\n");
        $zip = new \ZipArchive();
        $zip->open($this->zipPath);
        $zip->addFromString('restored.config.php', "<?php\n\$yeswikiConfig = " . var_export([
            'base_url' => 'https://there.test/?',
            'db_password' => '',
            'contact_smtp_pass' => '',
            'yeswiki_name' => 'From the backup',
            'favorite_theme' => 'margot',
        ], true) . ";\n");
        $zip->close();

        $previous = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE=' . $configFile);
        try {
            $zip->open($this->zipPath);
            $taken = (new \ReflectionMethod(ArchiveService::class, 'restoreConfiguration'))
                ->invoke($this->getWiki()->services->get(ArchiveService::class), $zip);
            $zip->close();
        } finally {
            putenv($previous === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $previous);
        }

        $restored = (new ConfigurationService())->getConfiguration($configFile);
        $restored->load();
        $this->assertSame(2, $taken);
        $this->assertSame('From the backup', $restored['yeswiki_name']);
        $this->assertSame('margot', $restored['favorite_theme']);
        $this->assertSame('https://here.test/?', $restored['base_url']);
        $this->assertSame('local-secret', $restored['db_password']);
        $this->assertSame('local-mail', $restored['contact_smtp_pass']);
    }

    public function testWhatTheWikiKeepsForItselfStays(): void
    {
        $zip = new \ZipArchive();
        $zip->open($this->zipPath);
        try {
            $this->getWiki()->services->get(ArchiveService::class)->removeFilesAbsentFromArchive($zip, $this->root);
        } finally {
            $zip->close();
        }

        $this->assertFileExists($this->root . '/cache/archive/output-uid.log', 'the restore reports its progress there');
        $this->assertFileExists($this->root . '/private/backups/2026-01-01T00-00-00_archive.zip');
        $this->assertFileExists($this->root . '/private/.env');
        $this->assertFileExists($this->root . '/tools/not-in-the-backup.php', 'a folder the backup does not hold is not its to empty');
        $this->assertFileExists($this->root . '/yeswiki.config.php');
    }
}
