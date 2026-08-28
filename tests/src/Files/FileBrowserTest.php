<?php

namespace YesWiki\Test\Files;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\FileBrowser;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The file manager only touches attachments on a POST carrying a valid token, and never outside the upload directory (GHSA-7m4h-m7qm-hc32).
 *
 * A valid token cannot be presented from the command line -- the checker reads the real request -- so the token cases here are refusals, and the operations themselves are exercised directly.
 */
class FileBrowserTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'FileManagerTestPage';
    private const FILE_NAME = 'FileManagerTestPage_sample_20260101000000_20260101000000.png';
    private const DECOY_NAME = 'FileManagerTestDecoy_x_20260101000000_20260101000000.pngtrash20260101000000';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private FileBrowser $browser;
    private Storage $storage;
    private string $uploadPath = '';
    private string $decoyPath = '';
    /** @var array<string, mixed> */
    private array $previousGet = [];
    /** @var array<string, mixed> */
    private array $previousPost = [];
    private ?string $previousMethod = null;
    private string $previousTag = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->storage = $this->wiki->services->get(Storage::class);

        $this->wiki->services->get(PageManager::class)->save(self::PAGE_TAG, [PageBody::CONTENT => 'page with files'], '', true);
        $pageContext = $this->wiki->services->get(PageContext::class);
        $this->previousTag = $pageContext->getTag();
        $pageContext->setTag(self::PAGE_TAG);

        $this->browser = $this->wiki->services->get(FileBrowser::class);
        $this->uploadPath = $this->wiki->services->get(AttachedFilePaths::class)->uploadPath();
        $this->decoyPath = sys_get_temp_dir() . '/' . self::DECOY_NAME;

        $this->previousGet = $_GET;
        $this->previousPost = $_POST;
        $this->previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->storage->write($this->filePath(), 'content');
        file_put_contents($this->decoyPath, 'decoy');
    }

    protected function tearDown(): void
    {
        foreach ($this->storage->glob($this->uploadPath . '/' . self::FILE_NAME . '*') as $leftover) {
            $this->storage->delete($leftover);
        }
        if (file_exists($this->decoyPath)) {
            unlink($this->decoyPath);
        }
        $this->wiki->services->get(PageManager::class)->deleteOrphaned(self::PAGE_TAG);
        $this->wiki->services->get(PageContext::class)->setTag($this->previousTag);

        $_GET = $this->previousGet;
        $_POST = $this->previousPost;
        if ($this->previousMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->previousMethod;
        }
        parent::tearDown();
    }

    private function filePath(string $suffix = ''): string
    {
        return $this->uploadPath . '/' . self::FILE_NAME . $suffix;
    }

    private function trashTheFile(): string
    {
        $trashName = self::FILE_NAME . 'trash20260101000000';
        $this->storage->move($this->filePath(), $this->uploadPath . '/' . $trashName);

        return $trashName;
    }

    public function testAGetRequestDoesNotEmptyTheTrash(): void
    {
        $trashName = $this->trashTheFile();
        $_GET['do'] = 'emptytrash';

        $this->browser->render();

        $this->assertTrue($this->storage->fileExists($this->uploadPath . '/' . $trashName));
    }

    public function testAGetRequestDoesNotDeleteAFile(): void
    {
        $_GET['do'] = 'del';
        $_GET['file'] = self::FILE_NAME;

        $this->browser->render();

        $this->assertTrue($this->storage->fileExists($this->filePath()));
    }

    public function testAPostWithoutATokenDeletesNothing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['do'] = 'del';
        $_POST['file'] = self::FILE_NAME;

        $output = $this->browser->render();

        $this->assertTrue($this->storage->fileExists($this->filePath()));
        $this->assertStringContainsString('yw-alert--danger', $output);
    }

    public function testTheFileManagerOffersPostFormsCarryingAToken(): void
    {
        $output = $this->browser->render();

        $this->assertStringContainsString(self::FILE_NAME, $output);
        $this->assertStringNotContainsString('do=del', $output, 'a GET link would delete on being followed');
        $this->assertMatchesRegularExpression(
            '/<form method="post"[^>]*>\s*<input type="hidden" name="csrf-token" value="[^"]+">/',
            $output,
            'the file manager delete button is not a POST form carrying a csrf token'
        );
    }

    public function testErasingRemovesTheTrashedFile(): void
    {
        $trashName = $this->trashTheFile();
        $_POST['file'] = $trashName;

        $this->browser->erase();

        $this->assertFalse($this->storage->fileExists($this->uploadPath . '/' . $trashName));
    }

    public function testErasingStaysInsideTheUploadDirectory(): void
    {
        $_POST['file'] = '../../' . self::DECOY_NAME;

        $this->browser->erase();

        $this->assertFileExists($this->decoyPath);
    }

    public function testRestoringStaysInsideTheUploadDirectory(): void
    {
        $_POST['file'] = $this->decoyPath;

        $this->browser->restore();

        $this->assertFileExists($this->decoyPath);
    }

    public function testRestoringBringsTheFileBack(): void
    {
        $this->trashTheFile();
        $_POST['file'] = self::FILE_NAME . 'trash20260101000000';

        $this->browser->restore();

        $this->assertTrue($this->storage->fileExists($this->filePath()));
    }
}
