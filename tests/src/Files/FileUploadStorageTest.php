<?php

namespace YesWiki\Test\Files;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use YesWiki\Content\Service\FileManager;
use YesWiki\Files\Service\Storage;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 41: an upload lands through Storage, under the Protected tier, and a deletion takes the bytes with it. */
class FileUploadStorageTest extends YesWikiTestCase
{
    public function testAnUploadIsStoredAndRemovedThroughStorage(): void
    {
        $services = self::getWiki()->services;
        $storage = $services->get(Storage::class);
        $files = $services->get(FileManager::class);

        $source = tempnam(sys_get_temp_dir(), 'yeswiki-upload');
        file_put_contents($source, 'the bytes that were uploaded');

        try {
            $attributes = $files->storeUpload(new UploadedFile($source, 'ticket-41.txt', 'text/plain', null, true));

            $stored = FileManager::STORAGE_DIR . '/' . $attributes['stored_filename'];
            $this->assertSame(Storage::PROTECTED_TIER, $storage->tierOf($stored));
            $this->assertTrue($storage->fileExists($stored));
            $this->assertSame('the bytes that were uploaded', $storage->read($stored));

            $entry = $files->create('ticket-41.txt', $attributes['stored_filename'], '', $attributes['size'], $attributes['mime_type']);
            $this->assertSame($stored, $files->getPhysicalPath($entry['tag']));

            $files->delete($entry['tag']);
            $this->assertFalse($storage->fileExists($stored));
        } finally {
            if (is_file($source)) {
                unlink($source);
            }
        }
    }
}
