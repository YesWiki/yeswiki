<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\Service\RemoteBackupService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(RemoteBackupService::class, 'start')]
#[CoversMethod(RemoteBackupService::class, 'status')]
class RemoteBackupServiceTest extends YesWikiTestCase
{
    private const GB = 1024 * 1024 * 1024;

    public function testLoggingInAsksTheRemoteForNothingYet()
    {
        $fetcher = $this->fetcher(['canArchive' => true, 'canExec' => true, 'enoughSpace' => true], 6 * self::GB);

        $state = $fetcher->start('https://remote.example', 'admin', 'secret');

        $this->assertSame(RemoteBackupService::STEP_CHECKING, $state['step']);
        $this->assertSame([], $fetcher->calls);
    }

    public function testARemoteShortOfSpaceIsRefusedBeforeItStartsAnything()
    {
        $fetcher = $this->fetcher(
            ['canArchive' => false, 'canExec' => true, 'archiving' => false, 'enoughSpace' => false, 'estimatedSize' => 5 * self::GB, 'freeSpace' => self::GB],
            10 * self::GB
        );

        $fetcher->start('https://remote.example', 'admin', 'secret');
        $state = $fetcher->status();

        $this->assertStringContainsString('remote wiki has not enough free space', $state['error']);
        $this->assertStringContainsString('5.0 GB needed, 1.0 GB free', $state['error']);
        $this->assertNotContains('startArchive', $fetcher->calls);
    }

    public function testALocalDiskShortOfSpaceIsRefusedBeforeTheRemoteStarts()
    {
        $fetcher = $this->fetcher(
            ['canArchive' => true, 'canExec' => true, 'enoughSpace' => true, 'estimatedSize' => 5 * self::GB, 'freeSpace' => 20 * self::GB],
            self::GB
        );

        $fetcher->start('https://remote.example', 'admin', 'secret');
        $state = $fetcher->status();

        $this->assertStringContainsString('Not enough free space here', $state['error']);
        $this->assertStringContainsString('5.0 GB needed, 1.0 GB free', $state['error']);
        $this->assertNotContains('startArchive', $fetcher->calls);
    }

    public function testEnoughSpaceOnBothSidesStartsTheRemoteArchive()
    {
        $fetcher = $this->fetcher(
            ['canArchive' => true, 'canExec' => true, 'enoughSpace' => true, 'estimatedSize' => 5 * self::GB, 'freeSpace' => 20 * self::GB],
            6 * self::GB
        );

        $fetcher->start('https://remote.example', 'admin', 'secret');

        $this->assertSame(RemoteBackupService::STEP_STARTING, $fetcher->status()['step']);
        $this->assertSame(RemoteBackupService::STEP_ARCHIVING, $fetcher->status()['step']);
        $this->assertContains('startArchive', $fetcher->calls);
    }

    public function testARemoteTooOldToSayItsSizeIsStillFetched()
    {
        $fetcher = $this->fetcher(['canArchive' => true, 'canExec' => true, 'enoughSpace' => true], 1);

        $fetcher->start('https://remote.example', 'admin', 'secret');
        $fetcher->status();
        $state = $fetcher->status();

        $this->assertSame(RemoteBackupService::STEP_ARCHIVING, $state['step']);
        $this->assertContains('startArchive', $fetcher->calls);
    }

    /**
     * A fetcher whose remote and disk are scripted, and whose job file is a variable.
     */
    private function fetcher(array $remoteStatus, ?int $localFree): RemoteBackupService
    {
        $archiveService = $this->getWiki()->services->get(ArchiveService::class);

        return new class($archiveService, $remoteStatus, $localFree) extends RemoteBackupService {
            public array $calls = [];
            private array $remoteStatus;
            private ?int $localFree;
            private array $job = [];

            public function __construct(ArchiveService $archiveService, array $remoteStatus, ?int $localFree)
            {
                parent::__construct($archiveService);
                $this->remoteStatus = $remoteStatus;
                $this->localFree = $localFree;
            }

            protected function login(string $baseUrl, string $username, string $password): string
            {
                return 'PHPSESSID=test';
            }

            protected function call(string $baseUrl, string $path, string $cookie, ?array $post = null): array
            {
                $this->calls[] = $post['action'] ?? $path;
                if ($path === 'api/archives/archivingStatus/') {
                    return $this->remoteStatus;
                }
                if (($post['action'] ?? '') === 'startArchive') {
                    return ['uid' => 'uid-test'];
                }

                return [];
            }

            protected function localFreeSpace(): ?int
            {
                return $this->localFree;
            }

            protected function readJob(): array
            {
                return $this->job;
            }

            protected function writeJob(array $job): void
            {
                $this->job = $job;
            }

            protected function deleteJob(): void
            {
                $this->job = [];
            }
        };
    }
}
