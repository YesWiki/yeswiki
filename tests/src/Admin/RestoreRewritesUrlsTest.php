<?php

namespace YesWiki\Test\Admin;

use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Database\DumpRewriter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Restoring a backup taken on another wiki points the addresses it carries at the wiki putting it back.
 *
 * The database is never written: the double captures the SQL at restoreDatabase(), which is the last step before the replay, so what is asserted is exactly what would have been replayed.
 */
class RestoreRewritesUrlsTest extends YesWikiTestCase
{
    private const SOURCE = 'https://somewhere-else.example/wiki/?';
    private const PAGE_URL = 'https://somewhere-else.example/wiki/PageOne';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private Storage $storage;
    /** @var list<string> */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->storage = $this->wiki->services->get(Storage::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if ($this->storage->fileExists($path)) {
                $this->storage->delete($path);
            }
        }
        $this->written = [];
        parent::tearDown();
    }

    private function service(): CapturingArchiveService
    {
        $services = $this->wiki->services;

        return new CapturingArchiveService(
            $services->get(\YesWiki\Kernel\Service\ConfigurationService::class),
            $services->get(\YesWiki\Kernel\Service\ConsoleService::class),
            $services->get(\YesWiki\Kernel\Service\DbService::class),
            $services->get(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class),
            $services->get(\YesWiki\Kernel\Service\HibernationService::class),
            $services->get(\YesWiki\Kernel\Service\UrlFormatter::class),
            $services->get(Storage::class),
            $services->get(\YesWiki\Files\Service\LocalFiles::class),
        );
    }

    /** A backup carrying the given source address, written where getFilePath() looks for one. */
    private function archiveNamed(string $filename, ?string $sourceBaseUrl): void
    {
        $local = tempnam(sys_get_temp_dir(), 'yw-restore-') . '.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($local, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true);
        $folder = ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP;
        $zip->addEmptyDir($folder);
        $zip->addFromString(
            $folder . '/' . ArchiveService::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP,
            "-- YesWiki-Dialect: sqlite\nINSERT INTO probe_pages VALUES ('" . self::PAGE_URL . "');\n"
        );
        if ($sourceBaseUrl !== null) {
            $zip->addFromString(
                $folder . '/' . ArchiveService::INFO_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP,
                (string)json_encode(['base_url' => $sourceBaseUrl])
            );
        }
        $zip->close();

        $path = $this->privateFolder() . '/' . $filename;
        $this->storage->write($path, (string)file_get_contents($local));
        unlink($local);
        $this->written[] = $path;
    }

    private function privateFolder(): string
    {
        return $this->wiki->services->get(ArchiveService::class)->getPrivateFolder();
    }

    private function here(): string
    {
        return DumpRewriter::root((string)$this->wiki->config['base_url']);
    }

    public function testABackupFromAnotherWikiHasItsAddressesRewritten(): void
    {
        $filename = 'restore-rewrite-test_archive_only_db.zip';
        $this->archiveNamed($filename, self::SOURCE);

        $service = $this->service();
        $service->restoreArchive($filename, false, true);

        $this->assertStringNotContainsString('somewhere-else.example', $service->replayed);
        $this->assertStringContainsString($this->here() . 'PageOne', $service->replayed);
    }

    public function testTheRewriteCanBeTurnedOff(): void
    {
        $filename = 'restore-norewrite-test_archive_only_db.zip';
        $this->archiveNamed($filename, self::SOURCE);

        $service = $this->service();
        $service->restoreArchive($filename, false, true, false);

        $this->assertStringContainsString(self::PAGE_URL, $service->replayed);
    }

    /** A backup written before restore.json existed simply says nothing, and is replayed as it always was. */
    public function testABackupThatDoesNotSayWhereItCameFromIsLeftAlone(): void
    {
        $filename = 'restore-noinfo-test_archive_only_db.zip';
        $this->archiveNamed($filename, null);

        $service = $this->service();
        $service->restoreArchive($filename, false, true);

        $this->assertStringContainsString(self::PAGE_URL, $service->replayed);
    }

    public function testTheSourceAddressCanBeReadBackFromAStoredBackup(): void
    {
        $filename = 'restore-source-test_archive_only_db.zip';
        $this->archiveNamed($filename, self::SOURCE);

        $this->assertSame(
            self::SOURCE,
            $this->wiki->services->get(ArchiveService::class)->getArchiveSourceBaseUrl($filename)
        );
    }
}

/** The real service, stopped at the last step before the replay so a test can read what would have been replayed. */
class CapturingArchiveService extends ArchiveService
{
    public string $replayed = '';

    protected function restoreDatabase(string $sqlContent): void
    {
        $this->replayed = $sqlContent;
    }
}
