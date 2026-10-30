<?php

namespace YesWiki\Test\Admin;

use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Whether there is room for an archive: measured where archives go, counting the database, and never blocking on a host that will not say. */
class ArchiveSpaceTest extends YesWikiTestCase
{
    private function service(?int $free): SpaceArchiveService
    {
        $services = $this->getWiki()->services;
        $service = new SpaceArchiveService(
            $services->get(\YesWiki\Kernel\Service\ConfigurationService::class),
            $services->get(\YesWiki\Kernel\Service\ConsoleService::class),
            $services->get(\YesWiki\Kernel\Service\DbService::class),
            $services->get(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class),
            $services->get(\YesWiki\Kernel\Service\HibernationService::class),
            $services->get(\YesWiki\Kernel\Service\UrlFormatter::class),
            $services->get(\YesWiki\Files\Service\Storage::class),
            $services->get(LocalFiles::class),
            $services->get(\Symfony\Component\Messenger\MessageBusInterface::class),
            $services->get(\YesWiki\Kernel\Service\Queue\QueueSettings::class),
            $services->get(\YesWiki\Kernel\Service\Queue\QueueRequestTrigger::class),
            $services->get(\YesWiki\Kernel\Service\Queue\QueueStatistics::class),
            $services->get(\YesWiki\Kernel\Service\Queue\QueueFallback::class),
        );
        $service->free = $free;

        return $service;
    }

    public function testTheEstimateCountsTheDatabase(): void
    {
        $service = $this->service(null);

        $this->assertGreaterThan(0, $service->databaseSizeForTest());
        $this->assertGreaterThanOrEqual(300 * 1024 * 1024 + $service->databaseSizeForTest(), $service->estimateArchiveSize());
    }

    public function testAnUnknownFreeSpaceDoesNotBlock(): void
    {
        $this->service(null)->assertEnoughSpaceForTest();
        $this->addToAssertionCount(1);
    }

    public function testTooLittleFreeSpaceBlocksAndSaysHowMuch(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/needed, 1 B free/');
        $this->service(1)->assertEnoughSpaceForTest();
    }

    public function testTheRealFreeSpaceIsMeasuredOnTheArchivesDisk(): void
    {
        $free = $this->getWiki()->services->get(ArchiveService::class)->freeSpaceForArchives();

        $this->assertTrue($free === null || $free > 0);
    }

    public function testARestoreCountsOnlyWhatItAddsToTheDisk(): void
    {
        $zip = $this->zip([
            'files/' . bin2hex(random_bytes(6)) . '.jpg' => str_repeat('n', 300),
            basename(\YesWiki\Kernel\Service\ConfigurationFileProvider::getConfigFileFromEnv()) => str_repeat('c', 5000),
            ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . ArchiveService::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP => str_repeat('s', 50),
        ]);
        $service = $this->service(null);

        try {
            $this->assertSame(300 + 50, $service->bytesToRestore($zip, true, true));
            $this->assertSame(300, $service->bytesToRestore($zip, true, false));
            $this->assertSame(50, $service->bytesToRestore($zip, false, true));
        } finally {
            $this->close($zip);
        }
    }

    public function testARestoreThatDoesNotFitIsRefusedBeforeItStarts(): void
    {
        $zip = $this->zip(['files/' . bin2hex(random_bytes(6)) . '.jpg' => str_repeat('n', 64 * 1024)]);

        try {
            $this->service(1024)->assertRoomToRestoreForTest($zip);
            $this->fail('a restore needing 64 kB with 1 kB free should be refused');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('Not enough free space to restore this backup', $exception->getMessage());
            $this->assertStringContainsString('1.0 kB free', $exception->getMessage());
        } finally {
            $this->close($zip);
        }
    }

    /** @param array<string, string> $entries */
    private function zip(array $entries): \ZipArchive
    {
        $path = sys_get_temp_dir() . '/restore-' . bin2hex(random_bytes(6)) . '.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
        $zip->open($path);

        return $zip;
    }

    private function close(\ZipArchive $zip): void
    {
        $path = $zip->filename;
        $zip->close();
        @unlink($path);
    }

    public function testLocalFilesSaysNullRatherThanFalseForAMissingFolder(): void
    {
        $this->assertNull((new LocalFiles())->freeSpace('/no/such/folder/' . bin2hex(random_bytes(4))));
    }
}

/** The real service with the free space it is told. */
class SpaceArchiveService extends ArchiveService
{
    public ?int $free = null;

    public function freeSpaceForArchives(): ?int
    {
        return $this->free;
    }

    public function databaseSizeForTest(): int
    {
        return $this->databaseSize();
    }

    public function assertRoomToRestoreForTest(\ZipArchive $zip): void
    {
        $this->assertRoomToRestore($zip, true, true);
    }

    public function assertEnoughSpaceForTest(): void
    {
        $this->assertEnoughtSpace();
    }
}
