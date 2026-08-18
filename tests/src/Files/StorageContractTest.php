<?php

namespace YesWiki\Test\Files;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Files\Entity\S3Settings;
use YesWiki\Files\Exception\StorageException;
use YesWiki\Files\Service\Storage;

/**
 * Ticket 42: the two homes a Data tier can have answer the same, and the places they could differ are named rather than assumed -- a missing object, a lease, a derived write that fails, a listing, a delete of nothing.
 */
class StorageContractTest extends TestCase
{
    private string $root = '';

    /**
     * @var list<string> what a run put in the bucket, so it can take it back out
     */
    private array $written = [];

    private ?Storage $remote = null;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yeswiki-contract-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            try {
                $this->remote?->delete($path);
            } catch (StorageException) {
            }
        }
        $this->written = [];
        if ($this->root !== '' && is_dir($this->root)) {
            exec('rm -rf ' . escapeshellarg($this->root));
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function homes(): array
    {
        return ['on this disk' => ['local'], 'in a bucket' => ['s3']];
    }

    #[DataProvider('homes')]
    public function testAnAbsentObjectIsAbsentNotAnError(string $home): void
    {
        $storage = $this->storage($home);

        $this->assertFalse($storage->exists($this->path('nothing.txt')));
        $this->assertFalse($storage->fileExists($this->path('nothing.txt')));
        $this->assertSame([], $storage->files($this->path('')));
    }

    #[DataProvider('homes')]
    public function testWhatWasWrittenComesBack(string $home): void
    {
        $storage = $this->storage($home);
        $path = $this->path('written.txt');

        $storage->write($path, 'bytes');
        $this->assertTrue($storage->fileExists($path));
        $this->assertSame('bytes', $storage->read($path));
        $this->assertSame(5, $storage->fileSize($path));
    }

    #[DataProvider('homes')]
    public function testAListingHoldsWhatWasPutInIt(string $home): void
    {
        $storage = $this->storage($home);
        $storage->write($this->path('listed/one.txt'), '1');
        $storage->write($this->path('listed/two.txt'), '2');
        $this->written[] = $this->path('listed/one.txt');
        $this->written[] = $this->path('listed/two.txt');

        $found = $storage->files($this->path('listed'), true);
        sort($found);

        $this->assertSame([$this->path('listed/one.txt'), $this->path('listed/two.txt')], $found);
    }

    #[DataProvider('homes')]
    public function testALeaseRoundTrips(string $home): void
    {
        $storage = $this->storage($home);
        $path = $this->path('leased.txt');
        $storage->write($path, 'before');

        $seen = $storage->withLocalCopy($path, static fn (string $local): string => (string)file_get_contents($local));
        $this->assertSame('before', $seen);

        $storage->withLocalTarget($path, static function (string $local): void {
            file_put_contents($local, file_get_contents($local) . ' and after');
        });
        $this->assertSame('before and after', $storage->read($path));
    }

    #[DataProvider('homes')]
    public function testADerivedWriteIsQuietButItsPathIsStillDeclared(string $home): void
    {
        $storage = $this->storage($home);

        $this->assertTrue($storage->storeDerived($this->path('derived.txt'), 'bytes'));
        $this->written[] = $this->path('derived.txt');

        $this->expectException(StorageException::class);
        $storage->storeDerived('nowhere-declared/derived.txt', 'bytes');
    }

    #[DataProvider('homes')]
    public function testDeletingWhatIsNotThereIsNotAnError(string $home): void
    {
        $storage = $this->storage($home);

        $storage->delete($this->path('never-written.txt'));
        $this->assertFalse($storage->exists($this->path('never-written.txt')));
    }

    #[DataProvider('homes')]
    public function testOnlyAPublicPathHasAUrl(string $home): void
    {
        $storage = $this->storage($home);

        $this->assertNotSame('', $storage->url('files/public.jpg'));

        $this->expectException(StorageException::class);
        $storage->url('private/files/secret.jpg');
    }

    public function testAPublicUrlPointsAtTheBucketAndAProtectedPathStaysHere(): void
    {
        $storage = $this->storage('s3');

        $this->assertStringStartsWith((string)self::publicUrl(), $storage->url('files/public.jpg'));
        $this->assertTrue($storage->isRemote('files/public.jpg'));
        $this->assertTrue($storage->isRemote('private/files/secret.jpg'));
        $this->assertFalse($storage->isRemote('private/yeswiki.db'));
    }

    public function testAPublicObjectIsFetchableWithoutASignature(): void
    {
        $storage = $this->storage('s3');
        $path = $this->path('anonymous.css');
        $storage->write($path, 'body{color:red}');
        $this->written[] = $path;

        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        $fetched = @file_get_contents($storage->url($path), false, $context);

        $this->assertSame('body{color:red}', $fetched, 'a Public object is served by URL, with the wiki out of the request');
    }

    public function testRuntimeIsRefusedObjectStorageByName(): void
    {
        putenv('YESWIKI_STORAGE=s3');
        putenv('YESWIKI_S3_TIERS=public,runtime');

        try {
            $this->expectException(StorageException::class);
            $this->expectExceptionMessageMatches('/private\/yeswiki\.db/');
            S3Settings::fromEnvironment();
        } finally {
            putenv('YESWIKI_STORAGE');
            putenv('YESWIKI_S3_TIERS');
        }
    }

    public function testAnUnknownBackendIsRefusedByName(): void
    {
        putenv('YESWIKI_STORAGE=ftp');

        try {
            $this->expectException(StorageException::class);
            $this->expectExceptionMessageMatches('/ftp/');
            S3Settings::fromEnvironment();
        } finally {
            putenv('YESWIKI_STORAGE');
        }
    }

    /** Every path a test writes is under one prefix, so a shared bucket survives a parallel run. */
    private function path(string $name): string
    {
        return rtrim('files/contract-' . getmypid() . '/' . $name, '/');
    }

    private function storage(string $home): Storage
    {
        if ($home === 'local') {
            return Storage::rootedAt($this->root);
        }

        $settings = self::bucketSettings();
        if ($settings === null) {
            $this->markTestSkipped('No S3 endpoint: set YESWIKI_TEST_S3_ENDPOINT (the compose stack runs SeaweedFS on 8333).');
        }
        $this->remote = Storage::rootedAtWith($this->root, $settings);

        return $this->remote;
    }

    /** @return non-empty-string */
    private static function publicUrl(): string
    {
        $url = rtrim((string)(getenv('YESWIKI_TEST_S3_PUBLIC_URL') ?: getenv('YESWIKI_TEST_S3_ENDPOINT') . '/yeswiki-test'), '/');

        return $url === '' ? 'http://s3.invalid/yeswiki-test' : $url;
    }

    private static function bucketSettings(): ?S3Settings
    {
        $endpoint = trim((string)getenv('YESWIKI_TEST_S3_ENDPOINT'));
        if ($endpoint === '') {
            return null;
        }

        return new S3Settings(
            bucket: (string)(getenv('YESWIKI_TEST_S3_BUCKET') ?: 'yeswiki-test'),
            region: (string)(getenv('YESWIKI_TEST_S3_REGION') ?: 'us-east-1'),
            endpoint: $endpoint,
            key: (string)(getenv('YESWIKI_TEST_S3_KEY') ?: 'yeswiki'),
            secret: (string)(getenv('YESWIKI_TEST_S3_SECRET') ?: 'yeswikisecret'),
            pathStyle: true,
            publicUrl: self::publicUrl(),
        );
    }
}
