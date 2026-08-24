<?php

namespace YesWiki\Test\Core\Services;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use YesWiki\Files\Service\BucketProvisioner;

#[CoversMethod(BucketProvisioner::class, 'provision')]
class BucketProvisionerTest extends TestCase
{
    /** @param array<string, mixed> $overrides */
    private function provisionFor(array $overrides = [], bool $mayReuse = false): void
    {
        (new BucketProvisioner())->provision('', '', sys_get_temp_dir(), array_merge([
            'storage' => 's3',
            's3_bucket' => 'wiki-one',
            's3_endpoint' => 'http://127.0.0.1:1',
            's3_key' => 'key',
            's3_secret' => 'secret',
            's3_public_url' => 'https://files.example.org',
        ], $overrides), $mayReuse);
    }

    public function testAWikiThatKeepsItsFilesLocallyHasNoBucketToMake(): void
    {
        $this->expectNotToPerformAssertions();

        (new BucketProvisioner())->provision('', '', sys_get_temp_dir(), []);
    }

    public function testANameThatIsNotABucketNameIsRefused(): void
    {
        foreach ([
            'Wiki-One',
            'wiki one',
            'wiki..one',
            '192.168.1.1',
            'ab',
            'wiki-',
            str_repeat('a', 64),
        ] as $hostile) {
            try {
                $this->provisionFor(['s3_bucket' => $hostile]);
                $this->fail("'$hostile' was accepted as a bucket name");
            } catch (\Throwable $refused) {
                $this->assertStringContainsString(
                    'is not a name a bucket can have',
                    $refused->getMessage(),
                    "'$hostile' must be refused before anything connects"
                );
            }
        }
    }

    public function testAnOrdinaryNameIsAccepted(): void
    {
        foreach (['wiki-one', 'files.example.org', 'w1k1'] as $ordinary) {
            $this->assertTrue(BucketProvisioner::isName($ordinary), "'$ordinary' is a bucket name");
        }
    }

    public function testAWikiWithNoBucketNamedIsToldWhichSettingIsMissing(): void
    {
        $this->expectExceptionMessageMatches('/s3_bucket \(or YESWIKI_S3_BUCKET\)/');

        $this->provisionFor(['s3_bucket' => '']);
    }
}
