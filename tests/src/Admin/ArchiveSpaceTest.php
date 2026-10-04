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

    public function assertEnoughSpaceForTest(): void
    {
        $this->assertEnoughtSpace();
    }
}
