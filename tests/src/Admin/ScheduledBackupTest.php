<?php

namespace YesWiki\Test\Admin;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Message\ScheduledArchive;
use YesWiki\Admin\Message\ScheduledArchiveHandler;
use YesWiki\Admin\Scheduler\AdminSchedule;
use YesWiki\Admin\Service\ArchiveFilename;
use YesWiki\Admin\Service\ArchiveSchedule;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Files\Service\Storage;
use YesWiki\Test\Core\ForcedParameterBag;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/ForcedParameterBag.php';

/** A backup taken every night, rotated apart from the manual and pre-update ones (end-users 12). */
class ScheduledBackupTest extends YesWikiTestCase
{
    /** @var list<string> */
    private array $written = [];

    protected function tearDown(): void
    {
        $storage = $this->getWiki()->services->get(Storage::class);
        $folder = $this->getWiki()->services->get(ArchiveService::class)->getPrivateFolder();
        foreach ($this->written as $name) {
            if ($storage->fileExists("$folder/$name")) {
                $storage->delete("$folder/$name");
            }
        }
        parent::tearDown();
    }

    /** @param array<string, mixed> $archive */
    private function schedule(array $archive): ArchiveSchedule
    {
        $real = $this->getWiki()->services->get(ParameterBagInterface::class);
        $current = $real->has('archive') && is_array($real->get('archive')) ? $real->get('archive') : [];

        return new ArchiveSchedule(new ForcedParameterBag($real, ['archive' => $archive + array_diff_key($current, array_flip(['schedule', 'schedule_type', 'scheduled_keep']))]));
    }

    public function testItRunsEveryNightByDefaultAndOffTurnsItOff(): void
    {
        $default = $this->schedule([]);
        $this->assertSame('30 3 * * *', $default->cron());
        $this->assertSame('only_db', $default->type());
        $this->assertSame(7, $default->keep());

        $this->assertSame('30 3 * * *', $this->schedule(['schedule' => ''])->cron(), 'an emptied field means the default');
        $this->assertSame('', $this->schedule(['schedule' => 'off'])->cron());
        $this->assertSame('', $this->schedule(['schedule' => 'every night'])->cron());
        $this->assertSame(7, $this->schedule(['scheduled_keep' => '0'])->keep());
        $this->assertSame('full', $this->schedule(['schedule_type' => 'full'])->type());
    }

    public function testTheEditorAcceptsOnlyACronExpressionOrOff(): void
    {
        $this->assertTrue(ArchiveSchedule::acceptsCron('0 2 * * 1'));
        $this->assertTrue(ArchiveSchedule::acceptsCron('off'));
        $this->assertTrue(ArchiveSchedule::acceptsCron(''));
        $this->assertFalse(ArchiveSchedule::acceptsCron('tous les soirs'));
    }

    public function testTheTaskIsDeclaredOnlyWhileTheScheduleIsOn(): void
    {
        $tasks = static fn (AdminSchedule $schedule): array => array_map(
            static fn ($recurring): string => [...$recurring->getProvider()->getMessages(new \Symfony\Component\Scheduler\Generator\MessageContext('', '', $recurring->getTrigger(), new \DateTimeImmutable()))][0]::class,
            $schedule->recurringMessages()
        );

        $this->assertContains(ScheduledArchive::class, $tasks(new AdminSchedule($this->schedule([]))));
        $this->assertNotContains(ScheduledArchive::class, $tasks(new AdminSchedule($this->schedule(['schedule' => 'off']))));
    }

    public function testAScheduledBackupIsNamedApart(): void
    {
        $name = ArchiveFilename::forNow('only_db', 'https://example.org/', true);

        $this->assertStringEndsWith('_example-org_archive_only_db_scheduled.zip', $name);
        $parts = ArchiveFilename::parse($name);
        $this->assertSame(['type' => 'only_db', 'scheduled' => true], array_intersect_key($parts, ['type' => 0, 'scheduled' => 0]));
        $this->assertFalse(ArchiveFilename::parse('2026-08-20T13-29-28_archive_only_db.zip')['scheduled'] ?? true);
        $this->assertStringEndsWith('_scheduled.zip', ArchiveFilename::withSource($name, 'https://other.org/'));
    }

    public function testEightNightsKeepSevenAndThePreUpdateBackup(): void
    {
        $services = $this->getWiki()->services;
        $archives = $services->get(ArchiveService::class);
        $storage = $services->get(Storage::class);
        $folder = $archives->getPrivateFolder();
        $baseUrl = $services->get(ParameterBagInterface::class)->get('base_url');
        $slug = ArchiveFilename::slug(is_string($baseUrl) ? $baseUrl : '');

        $preUpdate = "2099-01-01T00-00-00_{$slug}_archive.zip";
        $nights = [];
        for ($night = 1; $night <= 8; $night++) {
            $nights[] = sprintf('2099-01-%02dT03-30-00_%s_archive_only_db_scheduled.zip', $night, $slug);
        }
        foreach ([$preUpdate, ...$nights] as $name) {
            $storage->write("$folder/$name", 'zip');
            $this->written[] = $name;
        }

        $toDelete = $archives->archivesToDelete();

        $this->assertContains($nights[0], $toDelete, 'the oldest night goes');
        foreach (array_slice($nights, 1) as $kept) {
            $this->assertNotContains($kept, $toDelete);
        }
        $this->assertNotContains($preUpdate, $toDelete);
    }

    public function testTheTaskTakesADatabaseBackupMarkedScheduled(): void
    {
        $services = $this->getWiki()->services;
        $archives = $services->get(ArchiveService::class);
        $before = array_column($archives->getArchives(), 'filename');

        (new ScheduledArchiveHandler($archives, $this->schedule([])))(new ScheduledArchive());

        $new = array_values(array_diff(array_column($archives->getArchives(), 'filename'), $before));
        $this->written = [...$this->written, ...$new];
        $this->assertCount(1, $new);
        $this->assertStringEndsWith('_archive_only_db_scheduled.zip', $new[0]);
    }
}
