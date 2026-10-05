<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FileManager;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Pictures uploaded before Ectoplasme, rewritten as WebP and brought down to the upload bounds. */
class UploadedPicturesBecomeWebpTest extends YesWikiTestCase
{
    private const PAGE = 'UploadedPicturesBecomeWebpTestEntry';
    private const UPLOAD = 'files/UploadedPicturesBecomeWebpTestEntry_imagebf_image_poster_20260101000000_20260101000000.png';

    private Storage $storage;
    private FileManager $fileManager;
    private \UploadedPicturesBecomeWebp $migration;

    /** @var list<string> */
    private array $fileTags = [];

    /** @var list<string> */
    private array $paths = [];

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261005160000_UploadedPicturesBecomeWebp.php';
    }

    protected function setUp(): void
    {
        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('GD without WebP');
        }
        $services = $this->getWiki()->services;
        $this->storage = $services->get(Storage::class);
        $this->fileManager = $services->get(FileManager::class);
        $this->migration = new \UploadedPicturesBecomeWebp();
        $this->migration->setServices($services);
        $this->migration->setDbService($services->get(DbService::class));
    }

    protected function tearDown(): void
    {
        foreach ($this->fileTags as $tag) {
            $this->fileManager->delete($tag);
        }
        foreach ($this->paths as $path) {
            if ($this->storage->exists($path)) {
                $this->storage->delete($path);
            }
        }
        $db = $this->getWiki()->services->get(DbService::class);
        $db->query("DELETE FROM {$db->prefixTable('pages')} WHERE tag = ?", [self::PAGE]);
    }

    public function testALargeAttachedPictureIsFittedAndRewrittenAsWebp(): void
    {
        $tag = $this->attach('poster.png', 2600, 1300);

        $this->assertSame(1, $this->migration->shrinkFileContent([$tag], 1920, 1920, 82));

        $entry = $this->fileManager->getOne($tag);
        $this->assertNotNull($entry, 'the tag pages name still leads to the file');
        $this->assertSame('image/webp', $entry['mime_type']);
        $this->assertSame('poster.webp', $entry['original_filename']);
        $path = FileManager::STORAGE_DIR . '/' . $entry['stored_filename'];
        $this->paths[] = $path;
        $size = $this->storage->imageSize($path);
        $this->assertIsArray($size);
        $this->assertSame([1920, 960, IMAGETYPE_WEBP], [$size[0], $size[1], $size[2]]);
        $this->assertSame($this->storage->fileSize($path), $entry['size']);
        $this->assertFalse($this->storage->exists(FileManager::STORAGE_DIR . '/poster-large-pictures-test.png'), 'the original is gone');
    }

    public function testASmallPictureIsRewrittenAsWebpAtItsOwnSize(): void
    {
        $tag = $this->attach('small.png', 800, 600);

        $this->assertSame(1, $this->migration->shrinkFileContent([$tag], 1920, 1920, 82));

        $entry = $this->fileManager->getOne($tag);
        $this->assertNotNull($entry);
        $path = FileManager::STORAGE_DIR . '/' . $entry['stored_filename'];
        $this->paths[] = $path;
        $size = $this->storage->imageSize($path);
        $this->assertIsArray($size);
        $this->assertSame([800, 600, IMAGETYPE_WEBP], [$size[0], $size[1], $size[2]], 'converted, never enlarged');
    }

    public function testAGifIsLeftAsItIsForItsAnimation(): void
    {
        $image = imagecreatetruecolor(40, 40);
        ob_start();
        imagegif($image);
        $stored = 'anim-large-pictures-test.gif';
        $path = FileManager::STORAGE_DIR . '/' . $stored;
        $this->storage->write($path, (string)ob_get_clean());
        $this->paths[] = $path;
        $tag = (string)$this->fileManager->create('anim.gif', $stored, self::PAGE, $this->storage->fileSize($path), 'image/gif')['tag'];
        $this->fileTags[] = $tag;

        $this->assertSame(0, $this->migration->shrinkFileContent([$tag], 1920, 1920, 82));
        $this->assertTrue($this->storage->exists($path));
    }

    public function testABazarFieldPictureIsRenamedAndEveryContentNamingItFollows(): void
    {
        $this->storage->write(self::UPLOAD, $this->png(2600, 1300));
        $this->paths[] = self::UPLOAD;
        $old = basename(self::UPLOAD);
        $new = pathinfo($old, PATHINFO_FILENAME) . '.webp';
        $this->paths[] = 'files/' . $new;
        $db = $this->getWiki()->services->get(DbService::class);
        $db->query(
            "INSERT INTO {$db->prefixTable('pages')} (tag, {$db->quoteIdentifier('time')}, body, owner, {$db->quoteIdentifier('user')}, latest, type, parent)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [self::PAGE, '2026-01-01 00:00:00', PageBody::encode(['content' => "![affiche](files/{$old})", 'imagebf_image' => $old]), '', '', 'Y', 'page', '']
        );

        [$done, $touched] = $this->migration->shrinkUploads([self::UPLOAD], 1920, 1920, 82);

        $this->assertSame(1, $done);
        $this->assertSame([self::PAGE], $touched);
        $this->assertFalse($this->storage->exists(self::UPLOAD));
        $this->assertSame(IMAGETYPE_WEBP, $this->storage->imageSize('files/' . $new)[2] ?? null);
        $row = $db->loadSingle("SELECT body FROM {$db->prefixTable('pages')} WHERE tag = ?", [self::PAGE]);
        $this->assertNotNull($row);
        $body = PageBody::decode((string)($row['body'] ?? ''));
        $this->assertSame($new, $body['imagebf_image']);
        $this->assertSame("![affiche](files/{$new})", PageBody::content($body));
    }

    public function testAPictureGdCannotReadIsKeptAndTheNextOneIsStillConverted(): void
    {
        $broken = 'files/UploadedPicturesBecomeWebpTestEntry_imagebf_image_broken_20260101000000_20260101000000.jpg';
        $this->storage->write($broken, "\xFF\xD8\xFF\xC0\x00\x11\x08\x00\x40\x00\x40\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01" . str_repeat("\x00", 64));
        $this->paths[] = $broken;
        $this->paths[] = 'files/' . pathinfo($broken, PATHINFO_FILENAME) . '.webp';
        $this->storage->write(self::UPLOAD, $this->png(400, 300));
        $this->paths[] = self::UPLOAD;
        $this->paths[] = 'files/' . pathinfo(self::UPLOAD, PATHINFO_FILENAME) . '.webp';

        [$done] = @$this->migration->shrinkUploads([$broken, self::UPLOAD], 1920, 1920, 82);

        $this->assertSame(1, $done);
        $this->assertTrue($this->storage->exists($broken), 'the unreadable picture stays as it was');
        $this->assertFalse($this->storage->exists(self::UPLOAD));
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function attach(string $name, int $width, int $height): string
    {
        $stored = pathinfo($name, PATHINFO_FILENAME) . '-large-pictures-test.png';
        $path = FileManager::STORAGE_DIR . '/' . $stored;
        $this->storage->write($path, $this->png($width, $height));
        $this->paths[] = $path;
        $tag = (string)$this->fileManager->create($name, $stored, self::PAGE, $this->storage->fileSize($path), 'image/png')['tag'];
        $this->fileTags[] = $tag;

        return $tag;
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int)imagecolorallocate($image, 120, 60, 180));
        ob_start();
        imagepng($image);

        return (string)ob_get_clean();
    }
}
