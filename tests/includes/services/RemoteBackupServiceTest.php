<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\Service\RemoteBackupService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(RemoteBackupService::class, 'start')]
class RemoteBackupServiceTest extends YesWikiTestCase
{
    private const GB = 1024 * 1024 * 1024;

    public function testARemoteShortOfSpaceIsRefusedBeforeItStartsAnything()
    {
        $fetcher = $this->fetcher(
            ['canArchive' => false, 'canExec' => true, 'archiving' => false, 'enoughSpace' => false, 'estimatedSize' => 5 * self::GB, 'freeSpace' => self::GB],
            10 * self::GB
        );

        try {
            $fetcher->start('https://remote.example', 'admin', 'secret');
            $this->fail('the fetch should have been refused');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('remote wiki has not enough free space', $exception->getMessage());
            $this->assertStringContainsString('5.0 GB needed, 1.0 GB free', $exception->getMessage());
        }
        $this->assertNotContains('startArchive', $fetcher->calls);
    }

    public function testALocalDiskShortOfSpaceIsRefusedBeforeTheRemoteStarts()
    {
        $fetcher = $this->fetcher(
            ['canArchive' => true, 'canExec' => true, 'enoughSpace' => true, 'estimatedSize' => 5 * self::GB, 'freeSpace' => 20 * self::GB],
            self::GB
        );

        try {
            $fetcher->start('https://remote.example', 'admin', 'secret');
            $this->fail('the fetch should have been refused');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('Not enough free space here', $exception->getMessage());
            $this->assertStringContainsString('5.0 GB needed, 1.0 GB free', $exception->getMessage());
        }
        $this->assertNotContains('startArchive', $fetcher->calls);
    }

    public function testEnoughSpaceOnBothSidesStartsTheRemoteArchive()
    {
        $fetcher = $this->fetcher(
            ['canArchive' => true, 'canExec' => true, 'enoughSpace' => true, 'estimatedSize' => 5 * self::GB, 'freeSpace' => 20 * self::GB],
            6 * self::GB
        );

        $state = $fetcher->start('https://remote.example', 'admin', 'secret');

        $this->assertSame(RemoteBackupService::STEP_ARCHIVING, $state['step']);
        $this->assertContains('startArchive', $fetcher->calls);
    }

    public function testARemoteTooOldToSayItsSizeIsStillFetched()
    {
        $fetcher = $this->fetcher(['canArchive' => true, 'canExec' => true, 'enoughSpace' => true], 1);

        $state = $fetcher->start('https://remote.example', 'admin', 'secret');

        $this->assertSame(RemoteBackupService::STEP_ARCHIVING, $state['step']);
        $this->assertContains('startArchive', $fetcher->calls);
    }

    /**
     * A fetcher whose remote and disk are scripted, and that never touches the job file.
     */
    private function fetcher(array $remoteStatus, ?int $localFree): RemoteBackupService
    {
        $archiveService = $this->getWiki()->services->get(ArchiveService::class);

        return new class($archiveService, $remoteStatus, $localFree) extends RemoteBackupService {
            public array $calls = [];
            private array $remoteStatus;
            private ?int $localFree;

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
                return [];
            }

            protected function writeJob(array $job): void
            {
            }
        };
    }
}
