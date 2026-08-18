<?php

namespace YesWiki\Test\Files;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Files\Exception\StorageException;
use YesWiki\Files\Service\Storage;

/** Ticket 41: the path decides the tier, an unknown prefix is refused, and only a derived artefact may fail quietly (ADR-0022). */
class StorageTest extends TestCase
{
    private string $root = '';

    private Storage $storage;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yeswiki-storage-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0o755, true);
        $this->storage = Storage::rootedAt($this->root);
    }

    protected function tearDown(): void
    {
        if ($this->root !== '' && is_dir($this->root)) {
            exec('rm -rf ' . escapeshellarg($this->root));
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function tieredPaths(): array
    {
        return [
            'uploads are public' => ['files/PageX_photo.jpg', Storage::PUBLIC_TIER],
            'custom is public' => ['custom/styles/custom.css', Storage::PUBLIC_TIER],
            'a thumbnail is public' => ['cache/PageX_photo_vignette_140_140.webp', Storage::PUBLIC_TIER],
            'a fetched image is public' => ['cache/remote/abc.webp', Storage::PUBLIC_TIER],
            'attachments behind an acl are protected' => ['private/files/abc.webp', Storage::PROTECTED_TIER],
            'backups are protected' => ['private/backups/content.sql', Storage::PROTECTED_TIER],
            'digests are protected' => ['private/digests/2026-08.json', Storage::PROTECTED_TIER],
            'the database is runtime' => ['private/yeswiki.db', Storage::RUNTIME_TIER],
            'the search index is runtime' => ['private/search-indexes/pages/index', Storage::RUNTIME_TIER],
            'the compiled container is runtime' => ['cache/container/prod/X.php', Storage::RUNTIME_TIER],
            'compiled twig is runtime' => ['cache/templates/ab/cd.php', Storage::RUNTIME_TIER],
            'a lock is runtime' => ['cache/maintenance.lock', Storage::RUNTIME_TIER],
            'extensions are runtime' => ['custom/extensions/lms/index.php', Storage::RUNTIME_TIER],
            'the config is runtime' => ['yeswiki.config.php', Storage::RUNTIME_TIER],
        ];
    }

    #[DataProvider('tieredPaths')]
    public function testThePathDeclaresItsTier(string $path, string $tier): void
    {
        $this->assertSame($tier, $this->storage->tierOf($path));
    }

    public function testTheLongestPrefixWins(): void
    {
        $this->assertSame(Storage::PUBLIC_TIER, $this->storage->tierOf('custom/fonts/nunito.woff2'));
        $this->assertSame(Storage::RUNTIME_TIER, $this->storage->tierOf('custom/extensions/lms/desc.xml'));
    }

    public function testAPathUnderNoKnownRootIsRefusedByName(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessageMatches('/somewhere-else\/x\.txt/');
        $this->storage->tierOf('somewhere-else/x.txt');
    }

    public function testAnAbsoluteOrClimbingPathIsRefused(): void
    {
        $this->expectException(StorageException::class);
        $this->storage->read('files/../../etc/passwd');
    }

    public function testAPathInsideTheInstanceMayStillBeGivenInFull(): void
    {
        $this->storage->write('files/full.txt', 'here');

        $this->assertSame('here', $this->storage->read($this->root . '/files/full.txt'));
    }

    public function testAWriteThatDoesNotHappenThrows(): void
    {
        $this->storage->write('files/blocking.txt', 'a file where a directory has to go');

        $this->expectException(StorageException::class);
        $this->storage->write('files/blocking.txt/inside.txt', 'nope');
    }

    public function testADerivedArtefactMayFailQuietly(): void
    {
        $this->storage->write('cache/blocking.txt', 'a file where a directory has to go');

        $this->assertFalse($this->storage->storeDerived('cache/blocking.txt/thumb.webp', 'nope'));
        $this->assertTrue($this->storage->storeDerived('cache/thumb.webp', 'bytes'));
    }

    public function testEvenADerivedWriteRefusesAPathUnderNoTier(): void
    {
        $this->expectException(StorageException::class);
        $this->storage->storeDerived('nowhere-declared/thumb.webp', 'bytes');
    }

    public function testReadsWritesAndRemovals(): void
    {
        $this->assertFalse($this->storage->exists('files/x.txt'));
        $this->storage->write('files/x.txt', 'hello');
        $this->assertTrue($this->storage->exists('files/x.txt'));
        $this->assertSame('hello', $this->storage->read('files/x.txt'));
        $this->assertSame(5, $this->storage->fileSize('files/x.txt'));

        $this->storage->copy('files/x.txt', 'files/y.txt');
        $this->storage->move('files/y.txt', 'files/sub/z.txt');
        $this->assertSame(['files/sub/z.txt', 'files/x.txt'], $this->storage->files('files', true));

        $this->storage->delete('files/x.txt');
        $this->storage->deleteDirectory('files/sub');
        $this->assertSame([], $this->storage->files('files', true));
    }

    public function testOnlyAPublicPathHasAUrl(): void
    {
        $this->assertSame('files/x.jpg', $this->storage->url('files/x.jpg'));

        $this->expectException(StorageException::class);
        $this->storage->url('private/files/x.jpg');
    }

    public function testALeaseGivesARealFileAndTakesItBack(): void
    {
        $this->storage->write('files/leased.txt', 'bytes');

        $seen = $this->storage->withLocalCopy('files/leased.txt', function (string $local): string {
            $this->assertFileExists($local);

            return (string)file_get_contents($local);
        });
        $this->assertSame('bytes', $seen);

        $this->storage->withLocalTarget('cache/made/here.txt', function (string $local): void {
            file_put_contents($local, 'produced');
        });
        $this->assertSame('produced', $this->storage->read('cache/made/here.txt'));
    }
}
