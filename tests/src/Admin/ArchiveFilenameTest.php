<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\TestCase;
use YesWiki\Admin\Service\ArchiveFilename;
use YesWiki\Admin\Service\ArchiveService;

/** A backup is named after the wiki it was taken from, and still reads when it was not. */
class ArchiveFilenameTest extends TestCase
{
    public function testABackupWithoutSourceStillParses(): void
    {
        $this->assertSame(
            ['date' => '2026-08-20', 'time' => '13-29-28', 'source' => '', 'type' => 'full'],
            ArchiveFilename::parse('2026-08-20T13-29-28_archive.zip')
        );
        $this->assertSame('only_db', ArchiveFilename::parse('2026-08-20T13-29-28_archive_only_db.zip')['type'] ?? '');
    }

    public function testTheSourceIsRead(): void
    {
        $parts = ArchiveFilename::parse('2026-08-20T13-29-28_mydomain-ext-subfolder_archive_only_files.zip');

        $this->assertSame('mydomain-ext-subfolder', $parts['source'] ?? '');
        $this->assertSame('only_files', $parts['type']);
    }

    public function testWhatIsNoBackupIsRefused(): void
    {
        foreach (['content.sql', 'remote-backup.json', '2026-08-20T13-29-28_archive.zip.part', 'cloned-2026-08-20T13-29-28.zip', '2026-08-20T13-29-28_Upper_archive.zip'] as $name) {
            $this->assertSame([], ArchiveFilename::parse($name), $name);
        }
    }

    public function testTheSlugIsTheAddressMadeReadable(): void
    {
        $this->assertSame('mydomain-ext-subfolder', ArchiveFilename::slug('https://mydomain.ext/subfolder/?'));
        $this->assertSame('wiki-example-org', ArchiveFilename::slug('http://wiki.example.org/index.php?wiki='));
        $this->assertSame('localhost-8080', ArchiveFilename::slug('http://localhost:8080/'));
        $this->assertSame('', ArchiveFilename::slug(''));
        $this->assertLessThanOrEqual(ArchiveFilename::MAX_SOURCE_LENGTH, \strlen(ArchiveFilename::slug('https://' . str_repeat('a', 80) . '.org')));
    }

    public function testRenamingKeepsTheDateAndTheType(): void
    {
        $this->assertSame(
            '2026-08-20T13-29-28_other-org_archive_only_db.zip',
            ArchiveFilename::withSource('2026-08-20T13-29-28_mine-org_archive_only_db.zip', 'https://other.org')
        );
        $this->assertSame('2026-08-20T13-29-28_archive.zip', ArchiveFilename::withSource('2026-08-20T13-29-28_archive.zip', ''));
    }

    public function testANameMadeNowParsesBack(): void
    {
        $parts = ArchiveFilename::parse(ArchiveFilename::forNow('only_files', 'https://wiki.example.org/?'));

        $this->assertSame('wiki-example-org', $parts['source'] ?? '');
        $this->assertSame('only_files', $parts['type']);
    }

    public function testTheTypeIsReadFromEitherKindOfName(): void
    {
        $this->assertSame('only_db', ArchiveService::archiveType('2026-08-20T13-29-28_wiki-org_archive_only_db.zip'));
        $this->assertSame('only_files', ArchiveService::archiveType('restore-test_archive_only_files.zip'));
        $this->assertSame('full', ArchiveService::archiveType('2026-08-20T13-29-28_wiki-org_archive.zip'));
    }
}
