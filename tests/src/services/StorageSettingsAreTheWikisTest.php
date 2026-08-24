<?php

namespace YesWiki\Test\Core\Services;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use YesWiki\Files\Entity\S3Settings;

/** A wiki's storage is its own, not the process's. */
#[CoversMethod(S3Settings::class, 'fromConfiguration')]
#[CoversMethod(S3Settings::class, 'forInstance')]
class StorageSettingsAreTheWikisTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $before = [];

    /** @param array<string, string> $environment */
    private function withEnvironment(array $environment): void
    {
        foreach ($environment as $name => $value) {
            $this->before[$name] = getenv($name);
            putenv("$name=$value");
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->before as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv("$name=$value");
            }
        }
        $this->before = [];
    }

    /** @return array<string, string> */
    private function s3Configuration(string $bucket): array
    {
        return [
            'storage' => 's3',
            's3_bucket' => $bucket,
            's3_key' => 'key',
            's3_secret' => 'secret',
            's3_public_url' => 'https://files.example.org',
        ];
    }

    public function testAWikiWithNoStorageConfiguredKeepsItsFilesLocally(): void
    {
        $this->assertNull(S3Settings::fromConfiguration([]));
    }

    public function testTwoWikisInOneProcessGetTheirOwnBucket(): void
    {
        $one = S3Settings::fromConfiguration($this->s3Configuration('wiki-one'));
        $two = S3Settings::fromConfiguration($this->s3Configuration('wiki-two'));

        $this->assertNotNull($one);
        $this->assertNotNull($two);
        $this->assertSame('wiki-one', $one->bucket);
        $this->assertSame(
            'wiki-two',
            $two->bucket,
            'a farm gives every wiki its own bucket, which it cannot do while the bucket is a fact '
            . 'about the process rather than about the wiki'
        );
    }

    public function testTheWikiOutranksTheEnvironment(): void
    {
        $this->withEnvironment([
            'YESWIKI_STORAGE' => 's3',
            'YESWIKI_S3_BUCKET' => 'the-neighbours-bucket',
            'YESWIKI_S3_KEY' => 'key',
            'YESWIKI_S3_SECRET' => 'secret',
            'YESWIKI_S3_PUBLIC_URL' => 'https://files.example.org',
        ]);

        $settings = S3Settings::fromConfiguration($this->s3Configuration('my-own-bucket'));

        $this->assertNotNull($settings);
        $this->assertSame('my-own-bucket', $settings->bucket);
    }

    public function testTheEnvironmentStillAnswersWhenTheWikiSaysNothing(): void
    {
        $this->withEnvironment([
            'YESWIKI_STORAGE' => 's3',
            'YESWIKI_S3_BUCKET' => 'from-the-environment',
            'YESWIKI_S3_KEY' => 'key',
            'YESWIKI_S3_SECRET' => 'secret',
            'YESWIKI_S3_PUBLIC_URL' => 'https://files.example.org',
        ]);

        $settings = S3Settings::fromConfiguration([]);

        $this->assertNotNull($settings);
        $this->assertSame('from-the-environment', $settings->bucket);
        $this->assertEquals(S3Settings::fromEnvironment(), $settings);
    }

    /** The assertion a farm rests on: `YesWikiLoader::loadEnv()` puts the first instance served into the process environment, so an instance that reads its bucket from there reads its neighbour's. */
    public function testTwoInstancesInOneProcessReadTheirOwnEnvFile(): void
    {
        $alpha = $this->instanceKeeping('wiki-alpha');
        $beta = $this->instanceKeeping('wiki-beta');

        $this->withEnvironment(['YESWIKI_S3_BUCKET' => 'whichever-was-served-first']);

        $this->assertSame('wiki-alpha', S3Settings::forInstance($alpha)?->bucket);
        $this->assertSame('wiki-beta', S3Settings::forInstance($beta)?->bucket);
    }

    public function testAnInstanceWithNoEnvFileKeepsItsFilesLocally(): void
    {
        $empty = sys_get_temp_dir() . '/yeswiki-no-env-' . bin2hex(random_bytes(6));
        mkdir($empty . '/private', 0o700, true);

        $this->assertNull(S3Settings::forInstance($empty));
    }

    private function instanceKeeping(string $bucket): string
    {
        $dir = sys_get_temp_dir() . '/yeswiki-instance-' . bin2hex(random_bytes(6));
        mkdir($dir . '/private', 0o700, true);
        file_put_contents($dir . '/private/.env', implode("\n", [
            'YESWIKI_STORAGE=s3',
            "YESWIKI_S3_BUCKET=$bucket",
            'YESWIKI_S3_KEY=key',
            'YESWIKI_S3_SECRET=secret',
            'YESWIKI_S3_PUBLIC_URL=https://files.example.org',
        ]) . "\n");

        return $dir;
    }

    public function testWhatIsMissingIsNamedBothWays(): void
    {
        $this->expectExceptionMessageMatches('/s3_bucket \(or YESWIKI_S3_BUCKET\)/');

        S3Settings::fromConfiguration(['storage' => 's3']);
    }
}
