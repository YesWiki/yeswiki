<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Core\Service\ArchiveFilename;

require_once 'includes/services/ArchiveFilename.php';

class ArchiveFilenameTest extends TestCase
{
    #[DataProvider('nameProvider')]
    public function testParse(string $filename, array $expected)
    {
        $this->assertSame($expected, ArchiveFilename::parse($filename));
    }

    public static function nameProvider()
    {
        return [
            'a backup of this wiki' => [
                '2026-08-20T13-29-28_archive.zip',
                ['date' => '2026-08-20', 'time' => '13-29-28', 'source' => '', 'type' => 'full'],
            ],
            'database only' => [
                '2026-08-20T13-29-28_archive_only_db.zip',
                ['date' => '2026-08-20', 'time' => '13-29-28', 'source' => '', 'type' => 'only_db'],
            ],
            'files only' => [
                '2026-08-20T13-29-28_archive_only_files.zip',
                ['date' => '2026-08-20', 'time' => '13-29-28', 'source' => '', 'type' => 'only_files'],
            ],
            'fetched from another wiki' => [
                '2026-08-20T13-29-28_mydomain-ext-subfolder_archive.zip',
                ['date' => '2026-08-20', 'time' => '13-29-28', 'source' => 'mydomain-ext-subfolder', 'type' => 'full'],
            ],
            'fetched, database only' => [
                '2026-08-20T13-29-28_mydomain-ext_archive_only_db.zip',
                ['date' => '2026-08-20', 'time' => '13-29-28', 'source' => 'mydomain-ext', 'type' => 'only_db'],
            ],
        ];
    }

    #[DataProvider('notABackupProvider')]
    public function testWhatIsNotABackupIsNotParsed(string $filename)
    {
        $this->assertSame([], ArchiveFilename::parse($filename));
    }

    public static function notABackupProvider()
    {
        return [
            ['content.sql'],
            ['info.json'],
            ['README.md'],
            ['archive.zip'],
            ['2026-08-20T13-29-28_archive.zip.part'],
            ['2026-08-20T13-29-28_archive_only_pages.zip'],
            ['2026-08-20T13-29-28_MyDomain_archive.zip'],
            ['2026-08-20T13-29-28_my_domain_archive.zip'],
        ];
    }

    #[DataProvider('slugProvider')]
    public function testSlug(string $baseUrl, string $expected)
    {
        $this->assertSame($expected, ArchiveFilename::slug($baseUrl));
    }

    public static function slugProvider()
    {
        return [
            ['https://mydomain.ext/subfolder', 'mydomain-ext-subfolder'],
            ['https://mydomain.ext/subfolder/?', 'mydomain-ext-subfolder'],
            ['https://mydomain.ext/', 'mydomain-ext'],
            ['http://127.0.0.1:8898/', '127-0-0-1-8898'],
            ['https://Host.Example/Wiki/index.php?', 'host-example-wiki'],
            ['', ''],
            ['https://' . str_repeat('a', 60) . '.example/', str_repeat('a', 40)],
        ];
    }

    public function testWithSourceRenamesEachTypeOfBackup()
    {
        $this->assertSame(
            '2026-08-20T13-29-28_mydomain-ext-subfolder_archive.zip',
            ArchiveFilename::withSource('2026-08-20T13-29-28_archive.zip', 'https://mydomain.ext/subfolder/?')
        );
        $this->assertSame(
            '2026-08-20T13-29-28_mydomain-ext_archive_only_db.zip',
            ArchiveFilename::withSource('2026-08-20T13-29-28_archive_only_db.zip', 'https://mydomain.ext')
        );
    }

    public function testWithSourceReplacesASourceAlreadyThere()
    {
        $this->assertSame(
            '2026-08-20T13-29-28_second-example_archive.zip',
            ArchiveFilename::withSource('2026-08-20T13-29-28_first-example_archive.zip', 'https://second.example')
        );
    }

    #[DataProvider('unchangedProvider')]
    public function testNameIsLeftAlone(string $filename, string $baseUrl)
    {
        $this->assertSame($filename, ArchiveFilename::withSource($filename, $baseUrl));
    }

    public static function unchangedProvider()
    {
        return [
            'not a backup' => ['content.sql', 'https://mydomain.ext'],
            'no usable address' => ['2026-08-20T13-29-28_archive.zip', ''],
        ];
    }

    public function testARenamedBackupStillReadsBack()
    {
        $renamed = ArchiveFilename::withSource('2026-08-20T13-29-28_archive.zip', 'https://mydomain.ext/subfolder/?');

        $this->assertSame(
            ['date' => '2026-08-20', 'time' => '13-29-28', 'source' => 'mydomain-ext-subfolder', 'type' => 'full'],
            ArchiveFilename::parse($renamed)
        );
    }
}
