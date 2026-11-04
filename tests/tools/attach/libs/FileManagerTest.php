<?php

namespace YesWiki\Test\Attach\Libs;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The file manager only touches attachments on a POST carrying a valid token,
 * and never outside the page upload directory.
 */
class FileManagerTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'FileManagerTestPage';
    private const FILE_NAME = 'FileManagerTestPage_sample_20260101000000_20260101000000.png';
    private const DECOY_NAME = 'FileManagerTestDecoy_x_20260101000000_20260101000000.pngtrash20260101000000';

    private $wiki;
    private $attach;
    private $uploadPath;
    private $decoyPath;
    private $previousGet;
    private $previousPost;
    private $previousMethod;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $this->wiki->services->get(PageManager::class)->save(self::PAGE_TAG, 'page with files', '', true);
        $this->wiki->tag = self::PAGE_TAG;

        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        $this->attach = new \attach($this->wiki);
        $this->uploadPath = $this->attach->GetUploadPath();
        $this->decoyPath = sys_get_temp_dir() . '/' . self::DECOY_NAME;

        $this->previousGet = $_GET;
        $this->previousPost = $_POST;
        $this->previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        file_put_contents($this->filePath(), 'content');
        file_put_contents($this->decoyPath, 'decoy');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadPath . '/' . self::PAGE_TAG . '_*') as $leftover) {
            unlink($leftover);
        }
        if (file_exists($this->decoyPath)) {
            unlink($this->decoyPath);
        }
        $this->wiki->services->get(PageManager::class)->deleteOrphaned(self::PAGE_TAG);

        $_GET = $this->previousGet;
        $_POST = $this->previousPost;
        if ($this->previousMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->previousMethod;
        }
    }

    private function filePath(string $suffix = ''): string
    {
        return $this->uploadPath . '/' . self::FILE_NAME . $suffix;
    }

    private function trashTheFile(): string
    {
        $trashName = self::FILE_NAME . 'trash20260101000000';
        rename($this->filePath(), $this->uploadPath . '/' . $trashName);

        return $trashName;
    }

    private function runFileManager(): string
    {
        ob_start();
        $this->attach->doFileManager();

        return ob_get_clean();
    }

    public function testAGetRequestDoesNotEmptyTheTrash()
    {
        $trashName = $this->trashTheFile();
        $_GET['do'] = 'emptytrash';

        $this->runFileManager();

        $this->assertFileExists($this->uploadPath . '/' . $trashName);
    }

    public function testAPostWithoutATokenDeletesNothing()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['do'] = 'del';
        $_POST['file'] = self::FILE_NAME;

        $output = $this->runFileManager();

        $this->assertFileExists($this->filePath());
        $this->assertStringContainsString('alert-danger', $output);
    }

    public function testTheFileManagerOffersPostFormsCarryingAToken()
    {
        $output = $this->runFileManager();

        $this->assertStringContainsString(self::FILE_NAME, $output);
        $this->assertStringNotContainsString('do=del', $output);
        $this->assertMatchesRegularExpression(
            '/<form method="post"[^>]*>\s*<input type="hidden" name="csrf-token" value="[^"]+">/',
            $output,
            'file manager delete button is not a POST form carrying a csrf token'
        );
    }

    public function testDeletingTrashesAFileWhoseNameHasAccentsOrQuotes()
    {
        $name = "FileManagerTestPage_été_l'ami_20260101000000_20260101000000.png";
        file_put_contents($this->uploadPath . '/' . $name, 'content');
        $_POST['file'] = $name;

        $this->attach->fmDelete();

        $this->assertFileDoesNotExist($this->uploadPath . '/' . $name);
        $this->assertCount(1, glob($this->uploadPath . '/' . $name . 'trash*'));
    }

    public function testDeletingStaysInsideTheUploadDirectory()
    {
        $_POST['file'] = '../../' . self::DECOY_NAME;

        $this->attach->fmDelete();

        $this->assertFileExists($this->decoyPath);
        $this->assertEmpty(glob($this->decoyPath . 'trash*'));
    }

    public static function extensions(): array
    {
        return array_combine(
            ['png', 'mp3', 'mp4', 'wma', 'aac', 'webm', 'm4a', 'pdf_', 'MP3'],
            array_map(fn ($ext) => [$ext], ['png', 'mp3', 'mp4', 'wma', 'aac', 'webm', 'm4a', 'pdf_', 'MP3'])
        );
    }

    #[DataProvider('extensions')]
    public function testAFileIsListedThenTrashedThenRestoredWhateverItsExtension(string $ext)
    {
        $name = self::PAGE_TAG . '_media_20260101000000_20260101000000.' . $ext;
        $path = $this->uploadPath . '/' . $name;
        file_put_contents($path, 'content');

        $this->assertContains($name, $this->listedNames(false));
        $this->assertNotContains($name, $this->listedNames(true));

        $this->attach->fmDelete($name);
        $trashed = basename(glob($path . 'trash*')[0]);
        $this->assertNotContains($trashed, $this->listedNames(false));
        $this->assertContains($trashed, $this->listedNames(true));

        $_POST['file'] = $trashed;
        $this->attach->fmRestore();
        $this->assertFileExists($path);
        $this->assertContains($name, $this->listedNames(false));
    }

    private function listedNames(bool $trash): array
    {
        return array_column($this->attach->fmGetFiles($trash), 'realname');
    }

    public function testADotInThePageTagMatchesOnlyADot()
    {
        $this->wiki->tag = 'FileManager.estPage';
        $this->attach = new \attach($this->wiki);

        $this->assertNotContains(self::FILE_NAME, $this->listedNames(false));
    }

    public function testErasingRemovesTheTrashedFile()
    {
        $trashName = $this->trashTheFile();
        $_POST['file'] = $trashName;

        $this->attach->fmErase();

        $this->assertFileDoesNotExist($this->uploadPath . '/' . $trashName);
    }

    public function testErasingStaysInsideTheUploadDirectory()
    {
        $_POST['file'] = '../../' . self::DECOY_NAME;

        $this->attach->fmErase();

        $this->assertFileExists($this->decoyPath);
    }

    public function testRestoringStaysInsideTheUploadDirectory()
    {
        $_POST['file'] = $this->decoyPath;

        $this->attach->fmRestore();

        $this->assertFileExists($this->decoyPath);
    }

    public function testRestoringBringsTheFileBack()
    {
        $trashName = $this->trashTheFile();
        $_POST['file'] = $trashName;

        $this->attach->fmRestore();

        $this->assertFileExists($this->filePath());
    }
}
