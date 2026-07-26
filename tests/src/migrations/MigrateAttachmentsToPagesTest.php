<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\FileManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 17's MigrateAttachmentsToPages: converts pre-existing
 * physical uploads (legacy `{pageTag}_{name}_{pageDate}_{uploadDate}.{ext}` naming)
 * into FileManager file-entries and rewrites page bodies' `file="..."` references to
 * the new tag. Exercises the private migrateFiles()/rewritePageBodies() steps
 * directly against a throwaway fixture directory (via Reflection) rather than
 * run()'s real configured upload_path, so this never touches the dev instance's
 * actual files/ directory.
 */
class MigrateAttachmentsToPagesTest extends YesWikiTestCase
{
    private const OWNER_PAGE_TAG = 'MigrateAttachmentsToPagesTestOwnerPage';

    public static function setUpBeforeClass(): void
    {
        // YesWikiMigration (extended by the migration file below) is only autoloadable
        // once getWiki() has registered src/autoload.inc.php's fallback autoloader
        self::getWiki();
        require_once 'src/migrations/20260726000000_MigrateAttachmentsToPages.php';
    }

    public function testRecoverOriginalFilenameFlatSafeMode()
    {
        // spaces were already irreversibly turned into underscores at upload time
        // (Attach::GetFullFilename()'s str_replace(' ', '_', ...)), so the recovered
        // name keeps them as underscores too -- this is lossy by design, not a bug
        $this->assertSame(
            'my_document.txt',
            \MigrateAttachmentsToPages::recoverOriginalFilename(
                self::OWNER_PAGE_TAG . '_my_document_20250101000000_20250101000000.txt',
                self::OWNER_PAGE_TAG
            )
        );
    }

    public function testRecoverOriginalFilenameSubdirMode()
    {
        // no_safe_mode case: no page-tag prefix to strip, and non-media extensions get
        // a trailing underscore (see Attach::GetFullFilename())
        $this->assertSame(
            'report.pdf',
            \MigrateAttachmentsToPages::recoverOriginalFilename(
                'report_20250101000000_20250101000000.pdf_',
                null
            )
        );
    }

    public function testRecoverOriginalFilenameSkipsNonMatchingNames()
    {
        $this->assertNull(\MigrateAttachmentsToPages::recoverOriginalFilename('yeswiki-logo.png', null));
        $this->assertNull(\MigrateAttachmentsToPages::recoverOriginalFilename('README.md', null));
    }

    public function testMigrateFilesAndRewritePageBodies()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);
        $fileManager = $wiki->services->get(FileManager::class);

        $body = '{{attach file="' . self::OWNER_PAGE_TAG . '_my_report_20250101000000_20250101000000.txt" desc="report"}}';
        $pageManager->save(self::OWNER_PAGE_TAG, $body, '', true);
        $aclService->save(self::OWNER_PAGE_TAG, 'read', '@admins');

        $fixtureDir = sys_get_temp_dir() . '/MigrateAttachmentsToPagesTest-' . uniqid();
        mkdir($fixtureDir);
        $rawFilename = self::OWNER_PAGE_TAG . '_my_report_20250101000000_20250101000000.txt';
        $physicalPath = $fixtureDir . '/' . $rawFilename;
        file_put_contents($physicalPath, 'hello migration');

        $migration = new \MigrateAttachmentsToPages();
        $migration->setWiki($wiki);
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->setDbService($wiki->services->get(DbService::class));

        $migrateFiles = new \ReflectionMethod($migration, 'migrateFiles');
        $rewritePageBodies = new \ReflectionMethod($migration, 'rewritePageBodies');

        $newTag = null;
        try {
            $renameMap = $migrateFiles->invoke($migration, $fixtureDir, $fileManager, $pageManager, $aclService);
            $this->assertArrayHasKey($rawFilename, $renameMap);
            $newTag = $renameMap[$rawFilename];

            $this->assertTrue($fileManager->isFileTag($newTag));
            $entry = $fileManager->getOne($newTag);
            $this->assertSame('my_report.txt', $entry['original_filename']);
            $this->assertSame(self::OWNER_PAGE_TAG, $entry['uploaded_from']);
            $this->assertFileDoesNotExist($physicalPath, 'the legacy physical file should have been moved, not copied');
            $this->assertNotNull($fileManager->getPhysicalPath($newTag));

            $readAcl = $aclService->load($newTag, 'read');
            $this->assertSame('@admins', $readAcl['list'] ?? null, 'the new file entry should inherit the owning page\'s read ACL');

            $rewritePageBodies->invoke($migration, $renameMap, $pageManager);
            $rewritten = $pageManager->getOne(self::OWNER_PAGE_TAG, null, true, true);
            $this->assertStringContainsString('file="' . $newTag . '"', $rewritten['body']);
            $this->assertStringNotContainsString($rawFilename, $rewritten['body']);
        } finally {
            if (!is_null($newTag)) {
                $fileManager->delete($newTag);
            }
            if (file_exists($physicalPath)) {
                unlink($physicalPath);
            }
            if (is_dir($fixtureDir)) {
                rmdir($fixtureDir);
            }
        }
    }
}
