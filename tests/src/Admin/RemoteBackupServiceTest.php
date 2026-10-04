<?php

namespace YesWiki\Test\Admin;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Admin\Service\RemoteBackupService;
use YesWiki\Admin\Service\RemoteWikiArchive;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The admin screen's fetch: started once, advanced by each poll, and ending among this wiki's backups. */
class RemoteBackupServiceTest extends YesWikiTestCase
{
    private const REMOTE_NAME = '2026-08-20T13-29-28_archive.zip';

    private string $zipBytes = '';
    private bool $archiveMade = false;
    private string $stored = '';

    protected function setUp(): void
    {
        parent::setUp();
        $zipPath = sys_get_temp_dir() . '/yw-remote-backup-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('custom/remote.css', 'body {}');
        $zip->close();
        $this->zipBytes = (string)file_get_contents($zipPath);
        unlink($zipPath);
    }

    protected function tearDown(): void
    {
        $storage = $this->getWiki()->services->get(Storage::class);
        $folder = $this->getWiki()->services->get(ArchiveService::class)->getPrivateFolder();
        foreach ([$this->stored, RemoteBackupService::JOB_FILENAME] as $name) {
            if ($name !== '' && $storage->fileExists("$folder/$name")) {
                $storage->delete("$folder/$name");
            }
        }
        parent::tearDown();
    }

    private function service(): RemoteBackupService
    {
        $remote = new RemoteWikiArchive();
        (new \ReflectionProperty(RemoteWikiArchive::class, 'client'))->setValue($remote, new MockHttpClient(function (string $method, string $url): MockResponse {
            $query = (string)parse_url($url, PHP_URL_QUERY);
            $json = static fn (mixed $data, array $info = []): MockResponse => new MockResponse((string)json_encode($data), $info + ['http_code' => 200]);

            return match (true) {
                $query === 'api/login' => $json(['user' => 'RemoteAdmin', 'isAdmin' => true], ['response_headers' => ['Set-Cookie: YesWiki-main=abc; path=/']]),
                $query === 'api/archives/archivingStatus' => $json(['canArchive' => true]),
                $query === 'api/archives' && $method === 'GET' => $json($this->archiveMade ? [['filename' => self::REMOTE_NAME, 'type' => 'full', 'size' => \strlen($this->zipBytes)]] : []),
                $query === 'api/archives' && $method === 'POST' => (function () use ($json) {
                    $this->archiveMade = true;

                    return $json(['uid' => 'remoteuid', 'main' => true]);
                })(),
                $query === 'api/archives/uidstatus/remoteuid' => $json(['started' => true, 'finished' => true]),
                $query === 'api/archives/' . self::REMOTE_NAME => new MockResponse($this->zipBytes, ['http_code' => 200]),
                default => new MockResponse('', ['http_code' => 404]),
            };
        }));
        $services = $this->getWiki()->services;

        return new RemoteBackupService($services->get(ArchiveService::class), $services->get(Storage::class), $services->get(LocalFiles::class), $remote);
    }

    public function testTheFetchedBackupJoinsThisWikisBackups(): void
    {
        $service = $this->service();
        $state = $service->start('https://remote.org', 'RemoteAdmin', 'secret');
        $this->assertTrue($state['running']);
        $this->assertSame($state, $service->status(), 'reading the status does not move the fetch');

        $storage = $this->getWiki()->services->get(Storage::class);
        $folder = $this->getWiki()->services->get(ArchiveService::class)->getPrivateFolder();
        $this->assertStringNotContainsString('secret', $storage->read("$folder/" . RemoteBackupService::JOB_FILENAME), 'the password is not kept');

        for ($i = 0; $i < 20 && $state['running']; $i++) {
            $job = json_decode($storage->read("$folder/" . RemoteBackupService::JOB_FILENAME), true);
            if (isset($job['candidateSince'])) {
                $job['candidateSince'] = 0;
                $storage->write("$folder/" . RemoteBackupService::JOB_FILENAME, (string)json_encode($job));
            }
            $state = $service->advance();
        }

        $this->assertFalse($state['running'], json_encode($state) ?: '');
        $this->stored = $state['filename'];
        $this->assertSame('2026-08-20T13-29-28_remote-org_archive.zip', $this->stored);
        $this->assertSame($this->zipBytes, $storage->read("$folder/{$this->stored}"));
        $this->assertSame(['step' => RemoteBackupService::STEP_IDLE, 'running' => false], $service->status());
        $this->assertContains($this->stored, array_column($this->getWiki()->services->get(ArchiveService::class)->getArchives(), 'filename'));
        $this->assertNotContains($this->stored, $this->getWiki()->services->get(ArchiveService::class)->archivesToDelete(true), 'a fetched backup is not rotated away');
    }

    public function testASecondFetchWaitsForTheFirst(): void
    {
        $service = $this->service();
        $service->start('https://remote.org', 'RemoteAdmin', 'secret');

        try {
            $this->expectExceptionMessage('already running');
            $service->start('https://remote.org', 'RemoteAdmin', 'secret');
        } finally {
            $service->cancel();
        }
    }
}
