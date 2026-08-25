<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 17's MigrateAttachmentsToPages: converts pre-existing physical uploads (legacy `{pageTag}_{name}_{pageDate}_{uploadDate}.{ext}` naming) into FileManager file-entries and rewrites page bodies' `file="..."` references to the new tag.
 */
class MigrateAttachmentsToPagesTest extends YesWikiTestCase
{
    private const OWNER_PAGE_TAG = 'MigrateAttachmentsToPagesTestOwnerPage';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20260726000000_MigrateAttachmentsToPages.php';
    }

    public function testRecoverOriginalFilenameFlatSafeMode(): void
    {
        $this->assertSame(
            'my_document.txt',
            \MigrateAttachmentsToPages::recoverOriginalFilename(
                self::OWNER_PAGE_TAG . '_my_document_20250101000000_20250101000000.txt',
                self::OWNER_PAGE_TAG
            )
        );
    }

    public function testRecoverOriginalFilenameSubdirMode(): void
    {
        $this->assertSame(
            'report.pdf',
            \MigrateAttachmentsToPages::recoverOriginalFilename(
                'report_20250101000000_20250101000000.pdf_',
                null
            )
        );
    }

    public function testRecoverOriginalFilenameSkipsNonMatchingNames(): void
    {
        $this->assertNull(\MigrateAttachmentsToPages::recoverOriginalFilename('yeswiki-logo.png', null));
        $this->assertNull(\MigrateAttachmentsToPages::recoverOriginalFilename('README.md', null));
    }

    public function testMigrateFilesAndRewritePageBodies(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);
        $fileManager = $wiki->services->get(FileManager::class);

        $body = '{{attach file="my_report.txt" desc="report"}}';
        $pageManager->save(self::OWNER_PAGE_TAG, [PageBody::CONTENT => $body], '', true);
        $aclService->save(self::OWNER_PAGE_TAG, 'read', '@admins');

        // Under `files/`, not in the system temp directory. The migration reads a wiki's upload
        // path through Storage now, and a directory the wiki does not own has no tier -- so a
        // fixture in /tmp was testing a case this migration will never meet.
        $storage = $wiki->services->get(Storage::class);
        $fixtureDir = 'files/MigrateAttachmentsToPagesTest-' . uniqid();
        $rawFilename = self::OWNER_PAGE_TAG . '_my_report_20250101000000_20250101000000.txt';
        $physicalPath = $fixtureDir . '/' . $rawFilename;
        $storage->write($physicalPath, 'hello migration');

        $migration = new \MigrateAttachmentsToPages();
        $migration->setServices($wiki->services);
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->setDbService($wiki->services->get(DbService::class));

        $migrateFiles = new \ReflectionMethod($migration, 'migrateFiles');
        $rewritePageBodies = new \ReflectionMethod($migration, 'rewritePageBodies');

        $newTag = null;
        try {
            $renameMapByOwnerPage = $migrateFiles->invoke($migration, $fixtureDir, $fileManager, $pageManager);
            $this->assertArrayHasKey(self::OWNER_PAGE_TAG, $renameMapByOwnerPage);
            $this->assertArrayHasKey('my_report.txt', $renameMapByOwnerPage[self::OWNER_PAGE_TAG]);
            $newTag = $renameMapByOwnerPage[self::OWNER_PAGE_TAG]['my_report.txt'];

            $this->assertTrue($fileManager->isFileTag($newTag));
            $entry = $fileManager->getOne($newTag);
            $this->assertNotNull($entry, 'the migrated file must be readable back as a file entry');
            $this->assertSame('my_report.txt', $entry['original_filename']);
            $this->assertSame(self::OWNER_PAGE_TAG, $entry['uploaded_from']);
            $this->assertFalse($storage->exists($physicalPath), 'the legacy physical file should have been moved, not copied');
            $this->assertNotNull($fileManager->getPhysicalPath($newTag));

            $readAcl = $aclService->load($newTag, 'read');
            $this->assertSame('@admins', $readAcl['list'] ?? null, 'the new file entry should inherit the owning page\'s read ACL');

            $rewritePageBodies->invoke($migration, $renameMapByOwnerPage, $pageManager);
            $rewritten = $pageManager->getOne(self::OWNER_PAGE_TAG, null, true, true);
            $this->assertNotNull($rewritten);
            $this->assertStringContainsString('file="' . $newTag . '"', PageBody::content($rewritten['body']));
            $this->assertStringNotContainsString('file="my_report.txt"', PageBody::content($rewritten['body']));
        } finally {
            if (!is_null($newTag)) {
                $fileManager->delete($newTag);
            }
            if ($storage->exists($physicalPath)) {
                $storage->delete($physicalPath);
            }
            if ($storage->directoryExists($fixtureDir)) {
                $storage->deleteDirectory($fixtureDir);
            }
        }
    }

    public function testRewritePageBodiesDoesNotCollideAcrossPagesWithSameOriginalFilename(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $otherPageTag = self::OWNER_PAGE_TAG . 'Other';

        $pageManager->save(self::OWNER_PAGE_TAG, [PageBody::CONTENT => '{{attach file="shared.txt"}}'], '', true);
        $pageManager->save($otherPageTag, [PageBody::CONTENT => '{{attach file="shared.txt"}}'], '', true);

        $migration = new \MigrateAttachmentsToPages();
        $migration->setServices($wiki->services);
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->setDbService($wiki->services->get(DbService::class));
        $rewritePageBodies = new \ReflectionMethod($migration, 'rewritePageBodies');

        $renameMapByOwnerPage = [
            self::OWNER_PAGE_TAG => ['shared.txt' => 'tag-for-owner-page'],
            $otherPageTag => ['shared.txt' => 'tag-for-other-page'],
        ];

        try {
            $rewritePageBodies->invoke($migration, $renameMapByOwnerPage, $pageManager);

            $ownerPage = $pageManager->getOne(self::OWNER_PAGE_TAG, null, true, true);
            $otherPage = $pageManager->getOne($otherPageTag, null, true, true);
            $this->assertNotNull($ownerPage);
            $this->assertNotNull($otherPage);
            $ownerBody = PageBody::content($ownerPage['body']);
            $otherBody = PageBody::content($otherPage['body']);
            $this->assertStringContainsString('file="tag-for-owner-page"', $ownerBody);
            $this->assertStringContainsString('file="tag-for-other-page"', $otherBody);
        } finally {
            $pageManager->deleteOrphaned(self::OWNER_PAGE_TAG);
            $pageManager->deleteOrphaned($otherPageTag);
        }
    }
}
