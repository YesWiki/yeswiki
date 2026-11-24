<?php

namespace YesWiki\Test\Admin;

use YesWiki\Admin\Api\BackupsApiController;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Files\Service\Storage;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The backups screen is drawn on the server: the three parts, the list of what is kept, and what a refused start says. */
class BackupsScreenTest extends YesWikiTestCase
{
    private const ARCHIVE = '2001-02-03T04-05-06_archive_only_db.zip';

    private ?string $actorWas = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actorWas = $this->getWiki()->services->get(\YesWiki\Identity\Service\AuthenticationService::class)->getLoggedUserName() ?: null;
        self::actAs(self::adminName());
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $folder = $services->get(ArchiveService::class)->getPrivateFolder();
        if ($services->get(Storage::class)->fileExists($folder . '/' . self::ARCHIVE)) {
            $services->get(Storage::class)->delete($folder . '/' . self::ARCHIVE);
        }
        self::actAs($this->actorWas);
        parent::tearDown();
    }

    private function screen(): BackupsApiController
    {
        return $this->getWiki()->services->get(BackupsApiController::class);
    }

    public function testTheScreenIsDrawnWithoutVue(): void
    {
        $html = (string)$this->getWiki()->services->get(Performer::class)->run('adminbackups', 'action', []);

        $this->assertStringContainsString('id="backups-archive"', $html);
        $this->assertStringContainsString('id="backups-remote"', $html);
        $this->assertStringContainsString('id="backups-list"', $html);
        $this->assertStringNotContainsString('v-if', $html);
    }

    public function testAKeptArchiveIsListedWithWhatItHolds(): void
    {
        $services = $this->getWiki()->services;
        $folder = $services->get(ArchiveService::class)->getPrivateFolder();
        $services->get(Storage::class)->write($folder . '/' . self::ARCHIVE, str_repeat('x', 2048));

        $html = $this->screen()->listHtml();

        $this->assertStringContainsString(self::ARCHIVE, $html);
        $this->assertStringContainsString('2 K', $html);
        $this->assertStringContainsString('hx-post=', $html);
        $this->assertMatchesRegularExpression('/hx-confirm="[^"]*' . preg_quote(self::ARCHIVE, '/') . '/', $html, 'the restore asks first');
    }

    public function testSizesReadAsPeopleSayThem(): void
    {
        $this->assertSame('0', BackupsApiController::readableSize(0));
        $this->assertSame('512 ', BackupsApiController::readableSize(512));
        $this->assertSame('3 M', BackupsApiController::readableSize(3 * 1024 * 1024));
    }

    public function testAFetchFinishedWhileFollowedSaysSoAndReloadsTheList(): void
    {
        $response = $this->screen()->remoteHtml(['running' => false, 'step' => 'cleaning', 'filename' => 'x_archive.zip'], true);

        $this->assertStringContainsString('x_archive.zip', (string)$response->getContent());
        $this->assertSame('ywBackupsChanged', $response->headers->get('HX-Trigger'));
        $this->assertStringNotContainsString('every 1s', (string)$response->getContent(), 'a finished fetch is not asked about again');
    }

    public function testARunningFetchIsFollowed(): void
    {
        $html = (string)$this->screen()->remoteHtml(['running' => true, 'step' => 'downloading', 'bytes' => 1024, 'total' => 4096], false)->getContent();

        $this->assertStringContainsString('hx-trigger="every 1s"', $html);
        $this->assertStringContainsString('following=1', $html);
        $this->assertStringContainsString('<progress', $html);
    }

    public function testBeforeAnUpdateTheBackupStartsByItselfThenHandsOver(): void
    {
        $start = $this->screen()->archiveHtml(['state' => 'autostart', 'package' => 'yeswiki']);
        $this->assertStringContainsString('hx-trigger="load"', $start);
        $this->assertStringContainsString('api/admin/backups/start', $start);

        $handOver = $this->screen()->archiveHtml(['state' => 'updating', 'package' => 'yeswiki', 'updateUrl' => 'https://wiki.example/?Page&action=upgrade']);
        $this->assertStringContainsString('data-backups-autosubmit', $handOver);
        $this->assertStringContainsString('action="https://wiki.example/?Page&amp;action=upgrade"', $handOver);
        $this->assertStringContainsString('name="csrf-token"', $handOver);
    }
}
