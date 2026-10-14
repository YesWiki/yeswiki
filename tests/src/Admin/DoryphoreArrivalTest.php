<?php

namespace YesWiki\Test\Admin;

use YesWiki\Admin\Entity\PackageCore;
use YesWiki\Admin\Service\AutoUpdateService;
use YesWiki\Admin\Service\DoryphoreArrival;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Entity\Messages;
use YesWiki\Kernel\Service\DoryphoreLeftovers;
use YesWiki\Kernel\Service\MigrationService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** What a request does to a wiki Doryphore's update screen just turned into Ectoplasme. */
class DoryphoreArrivalTest extends YesWikiTestCase
{
    private string $instance;

    private string $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getWiki();
        $this->instance = sys_get_temp_dir() . '/doryphore-arrival-' . bin2hex(random_bytes(4));
        $this->program = $this->instance . '/program';
        mkdir($this->instance . '/' . dirname(DoryphoreLeftovers::PENDING), 0755, true);
        mkdir($this->program, 0755, true);
        touch($this->instance . '/' . DoryphoreLeftovers::PENDING);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->instance));
        parent::tearDown();
    }

    private function withTheme(): void
    {
        mkdir($this->program . '/themes/' . THEME_PAR_DEFAUT, 0755, true);
    }

    private function migrationsAnswering(Messages $messages): MigrationService
    {
        $migrations = $this->createMock(MigrationService::class);
        $migrations->expects($this->once())->method('run')->willReturn($messages);

        return $migrations;
    }

    private function noMigrations(): MigrationService
    {
        $migrations = $this->createMock(MigrationService::class);
        $migrations->expects($this->never())->method('run');

        return $migrations;
    }

    private function noUpdates(): AutoUpdateService
    {
        $updates = $this->createMock(AutoUpdateService::class);
        $updates->expects($this->never())->method('upgrade');

        return $updates;
    }

    private function arrival(AutoUpdateService $updates, MigrationService $migrations): DoryphoreArrival
    {
        return new DoryphoreArrival($updates, $migrations, new RuntimeLock(), new LocalFiles());
    }

    public function testAWikiWithNothingPendingIsLeftAlone(): void
    {
        unlink($this->instance . '/' . DoryphoreLeftovers::PENDING);

        [$state] = $this->arrival($this->noUpdates(), $this->noMigrations())->finish($this->instance, $this->program);

        $this->assertSame(DoryphoreArrival::IDLE, $state);
    }

    public function testAMissingDefaultThemeIsReinstalledBeforeAnyMigrationRuns(): void
    {
        $updates = $this->createMock(AutoUpdateService::class);
        $updates->method('mayUpgrade')->willReturn(true);
        $updates->method('initRepository')->willReturn(true);
        $updates->expects($this->once())->method('upgrade')->with(PackageCore::CORE_NAME)
            ->willReturn((new Messages())->add('AU_UPDATE_THEME', 'AU_OK'));

        [$state] = $this->arrival($updates, $this->noMigrations())->finish($this->instance, $this->program);

        $this->assertSame(DoryphoreArrival::AGAIN, $state);
        $this->assertTrue($this->arrival($this->noUpdates(), $this->noMigrations())->isPending($this->instance));
    }

    public function testWithTheThemeInPlaceTheMigrationsRunAndTheMoveIsOver(): void
    {
        $this->withTheme();

        [$state, $messages] = $this->arrival($this->noUpdates(), $this->migrationsAnswering((new Messages())->add('Migration X', 'AU_OK')))
            ->finish($this->instance, $this->program);

        $this->assertSame(DoryphoreArrival::DONE, $state);
        $this->assertFalse($messages->hasError());
        $this->assertFalse($this->arrival($this->noUpdates(), $this->noMigrations())->isPending($this->instance));
    }

    public function testAFailedMigrationLeavesTheMovePendingForTheNextRequest(): void
    {
        $this->withTheme();

        [$state, $messages] = $this->arrival($this->noUpdates(), $this->migrationsAnswering((new Messages())->add('Migration X', 'AU_ERROR')))
            ->finish($this->instance, $this->program);

        $this->assertSame(DoryphoreArrival::DONE, $state);
        $this->assertTrue($messages->hasError());
        $this->assertTrue($this->arrival($this->noUpdates(), $this->noMigrations())->isPending($this->instance));
    }

    public function testAFarmInstanceNeverReinstallsTheSharedProgram(): void
    {
        $updates = $this->createMock(AutoUpdateService::class);
        $updates->method('mayUpgrade')->willReturn(false);
        $updates->expects($this->never())->method('upgrade');

        [$state, $messages] = $this->arrival($updates, $this->noMigrations())->finish($this->instance, $this->program);

        $this->assertSame(DoryphoreArrival::DONE, $state);
        $this->assertTrue($messages->hasError());
        $this->assertTrue($this->arrival($this->noUpdates(), $this->noMigrations())->isPending($this->instance));
    }

    public function testAnUnreachableRepositoryIsSaidAndNothingIsCopied(): void
    {
        $updates = $this->createMock(AutoUpdateService::class);
        $updates->method('mayUpgrade')->willReturn(true);
        $updates->method('initRepository')->willReturn(false);
        $updates->expects($this->never())->method('upgrade');

        [$state, $messages] = $this->arrival($updates, $this->noMigrations())->finish($this->instance, $this->program);

        $this->assertSame(DoryphoreArrival::DONE, $state);
        $this->assertTrue($messages->hasError());
    }

    public function testASecondRequestWaitsWhileTheFirstIsFinishing(): void
    {
        $locks = new RuntimeLock();
        $held = $locks->acquire(DoryphoreArrival::LOCK);
        $this->assertTrue($locks->tryLock($held));
        try {
            [$state] = $this->arrival($this->noUpdates(), $this->noMigrations())->finish($this->instance, $this->program);
        } finally {
            $locks->release($held);
        }

        $this->assertSame(DoryphoreArrival::BUSY, $state);
    }
}
