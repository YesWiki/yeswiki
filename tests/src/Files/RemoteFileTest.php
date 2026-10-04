<?php

namespace YesWiki\Test\Files;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Files\Service\RemoteFile;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A file named in an imported CSV is fetched from a public address or not at all, and a failure says so to the caller rather than to the page.
 */
class RemoteFileTest extends YesWikiTestCase
{
    private const TARGET = 'files/RemoteFileTest_download.png';

    protected function tearDown(): void
    {
        @unlink(self::TARGET);
        parent::tearDown();
    }

    #[DataProvider('refusedProvider')]
    public function testNothingIsWrittenAndNothingIsEchoedWhenTheFetchFails(string $url, string $because): void
    {
        $this->getWiki();
        $this->expectOutputString('');

        $copied = RemoteFile::download($url, self::TARGET, $error);

        $this->assertFalse($copied);
        $this->assertStringContainsString($because, (string)$error);
        $this->assertFileDoesNotExist(self::TARGET);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function refusedProvider(): array
    {
        return [
            'a plain file name' => ['photo.png', 'Invalid URL'],
            'loopback by name' => ['http://localhost:1/photo.png', 'private or reserved'],
            'the cloud metadata address' => ['http://169.254.169.254/latest/meta-data/photo.png', 'private or reserved'],
            'a local file' => ['file:///etc/passwd', 'Invalid URL'],
        ];
    }
}
