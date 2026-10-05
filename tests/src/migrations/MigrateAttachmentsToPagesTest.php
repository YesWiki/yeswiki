<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\LegacyAttachments;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A fake Doryphore upload directory turned into File Content, with every reference to it rewritten by Doryphore's own lookup rules.
 */
class MigrateAttachmentsToPagesTest extends YesWikiTestCase
{
    private Storage $storage;
    private PageManager $pageManager;
    private FileManager $fileManager;
    private LegacyAttachments $legacy;
    private string $fixtureDir;

    /** @var list<string> */
    private array $pages = [];

    protected function setUp(): void
    {
        $services = $this->getWiki()->services;
        $this->storage = $services->get(Storage::class);
        $this->pageManager = $services->get(PageManager::class);
        $this->fileManager = $services->get(FileManager::class);
        $this->legacy = new LegacyAttachments($this->fileManager, $this->pageManager, $this->storage, $services->get(DbService::class));
        $this->fixtureDir = 'files/MigrateAttachmentsToPagesTest-' . uniqid();
    }

    protected function tearDown(): void
    {
        foreach ($this->fileTagsOwnedBy($this->pages) as $tag) {
            $this->fileManager->delete($tag);
        }
        foreach ($this->pages as $tag) {
            $this->pageManager->deleteOrphaned($tag);
        }
        if ($this->storage->directoryExists($this->fixtureDir)) {
            $this->storage->deleteDirectory($this->fixtureDir);
        }
    }

    public function testRecoverOriginalFilename(): void
    {
        $this->assertSame('my_document.txt', LegacyAttachments::recoverOriginalFilename('Owner_my_document_20250101000000_20250101000000.txt', 'Owner'));
        $this->assertSame('report.pdf', LegacyAttachments::recoverOriginalFilename('report_20250101000000_20250101000000.pdf_', null));
        $this->assertNull(LegacyAttachments::recoverOriginalFilename('yeswiki-logo.png', null));
        $this->assertNull(LegacyAttachments::recoverOriginalFilename('report_20250101000000_20250101000000.pdf_trash20250102000000', null));
    }

    public function testKeysFollowDoryphoreSanitiser(): void
    {
        $this->assertSame('bas_page_robustesse_wiki2.jpg', LegacyAttachments::doryphoreKey('bas page robustesse wiki2.jpg'));
        $this->assertSame('Runion.pdf', LegacyAttachments::doryphoreKey('Réunion.pdf'));
        $this->assertSame(LegacyAttachments::foldedKey('Réunion d\'été.pdf'), LegacyAttachments::foldedKey('reunion_dete.pdf'));
    }

    public function testRewriteTextLeavesCommentsAndOtherParametersAlone(): void
    {
        $resolve = static fn (string $value): ?string => $value === 'a b.png' ? 'a-b' : null;
        $text = "{{attach file=\"a b.png\" desc=\"x\"}}\n{# {{section file=\"a b.png\"}} #}\n{{video datafile=\"a b.png\"}} file=\"a b.png\"\n{# open {{attach file=\"a b.png\"}}";

        $this->assertSame(
            "{{attach file=\"a-b\" desc=\"x\"}}\n{# {{section file=\"a b.png\"}} #}\n{{video datafile=\"a b.png\"}} file=\"a b.png\"\n{# open {{attach file=\"a b.png\"}}",
            LegacyAttachments::rewriteText($text, $resolve)
        );
    }

    public function testMigrationMakesOneFileContentPerNameAndRewritesEveryReference(): void
    {
        $owner = $this->page('MatpOwner', '');
        $cross = $this->page('MatpCross', "{{attach file=\"{$owner}/banner_R4.png\"}}");
        $host = $this->page('MatpHost', '');
        $footer = $this->page('MatpFooter', '{{attach file="logo.png"}}');
        $subdirOwner = $this->page('MatpSubdir', '{{attach file="report.pdf"}}');
        $entry = $this->page('MatpEntry', '', PageType::ENTRY);
        $entryFile = "{$entry}_imagebf_image_photo_20240101000000_20240101000000.jpg";
        $this->pageManager->save($entry, ['bf_titre' => 'x', 'form_id' => '1', 'imagebf_image' => $entryFile], '', true, null, PageType::ENTRY);
        $this->pageManager->save($host, [PageBody::CONTENT => "{{include page=\"{$footer}\"}}"], '', true);
        $this->pageManager->save($owner, [PageBody::CONTENT => implode("\n", [
            '{{attach file="banner_R4.png"}}',
            '{{section class="cover full-width" file="bas page robustesse wiki2.jpg" height="400"}}x{{end elem="section"}}',
            '{# {{section file="bandeau.webp"}} #}',
            '{{attach file="missing.txt"}}',
        ])], '', true);

        $old = "{$this->fixtureDir}/{$owner}_banner_R4_20240101000000_20240101000000.png";
        $orphan = "{$this->fixtureDir}/MatpNoSuchPage" . uniqid() . '_x_20240101000000_20240101000000.png';
        $this->storage->write($old, 'old');
        $this->storage->write("{$this->fixtureDir}/{$owner}_banner_R4_20240301000000_20240301000000.png", 'new');
        $this->storage->write("{$this->fixtureDir}/{$owner}_bas_page_robustesse_wiki2_20240530102201_20240530102207.jpg", 'bas');
        $this->storage->write("{$this->fixtureDir}/{$owner}_bandeau_20240101000000_20240101000000.webp", 'bandeau');
        $this->storage->write("{$this->fixtureDir}/{$host}_logo_20240101000000_20240101000000.png", 'logo');
        $this->storage->write("{$this->fixtureDir}/{$subdirOwner}/report_20250101000000_20250101000000.pdf_", 'report');
        $this->storage->write("{$this->fixtureDir}/{$entryFile}", 'photo');
        $this->storage->write($orphan, 'orphan');

        $report = $this->legacy->migrateUploads($this->fixtureDir);
        $rewrite = $this->legacy->rewriteReferences($this->pages);

        $this->assertCount(5, $report['created']);
        $this->assertSame([$old], $report['superseded']);
        $this->assertSame([$orphan], $report['orphans']);
        $this->assertSame(["{$this->fixtureDir}/{$entryFile}"], $report['namedVerbatim']);
        $this->assertTrue($this->storage->exists($old), 'an upload a newer one replaced stays where it was');
        $this->assertTrue($this->storage->exists("{$this->fixtureDir}/{$entryFile}"), 'a Bazar field file stays where its entry reads it');

        $banner = $this->fileOf($owner, 'banner_R4.png');
        $this->assertCount(1, $banner, 'two uploads of one name give one File Content');
        $this->assertSame('new', $this->storage->read((string)$this->fileManager->getPhysicalPath($banner[0])));
        [$bas] = $this->fileOf($owner, 'bas_page_robustesse_wiki2.jpg');
        [$logo] = $this->fileOf($host, 'logo.png');
        [$reportTag] = $this->fileOf($subdirOwner, 'report.pdf');

        $ownerBody = $this->content($owner);
        $this->assertStringContainsString("{{attach file=\"{$banner[0]}\"}}", $ownerBody);
        $this->assertStringContainsString("file=\"{$bas}\" height=\"400\"", $ownerBody);
        $this->assertStringContainsString('{# {{section file="bandeau.webp"}} #}', $ownerBody);
        $this->assertStringContainsString('{{attach file="missing.txt"}}', $ownerBody);
        $this->assertSame("{{attach file=\"{$banner[0]}\"}}", $this->content($cross));
        $this->assertSame("{{attach file=\"{$logo}\"}}", $this->content($footer), 'an included page finds what was uploaded on the page including it');
        $this->assertSame("{{attach file=\"{$reportTag}\"}}", $this->content($subdirOwner));
        $this->assertSame($entryFile, $this->pageManager->getOne($entry, null, false, true)['body']['imagebf_image'] ?? null);
        $this->assertContains("{$owner}: missing.txt", $rewrite['unresolved']);
    }

    public function testNewFileContentInheritsTheOwnerPageReadAcl(): void
    {
        $owner = $this->page('MatpAcl', '{{attach file="my_report.txt"}}');
        $this->getWiki()->services->get(AclService::class)->save($owner, 'read', '@admins');
        $this->storage->write("{$this->fixtureDir}/{$owner}_my_report_20250101000000_20250101000000.txt", 'hello');

        $report = $this->legacy->migrateUploads($this->fixtureDir);
        $tag = array_values($report['created'])[0];

        $this->assertSame('@admins', $this->getWiki()->services->get(AclService::class)->load($tag, 'read')['list'] ?? null);
        $this->assertSame(FileManager::STORAGE_DIR . '/' . ($this->fileManager->getOne($tag)['stored_filename'] ?? ''), $this->fileManager->getPhysicalPath($tag));
    }

    public function testRepairOnAWikiTheFirstMigrationLeftBroken(): void
    {
        $footer = $this->page('MatpBrokenFooter', '');
        $supporters = $this->page('MatpSupporters', '');
        $entry = $this->page('MatpBrokenEntry', '', PageType::ENTRY);
        $older = $this->fileContent($footer, 'banner_R4.png', 'old');
        $newer = $this->fileContent($footer, 'banner_R4.png', 'new');
        $bas = $this->fileContent($footer, 'bas_page_robustesse_wiki2.jpg', 'bas');
        $photo = $this->fileContent($entry, 'imagebf_image_photo.jpg', 'photo');
        $entryFile = "{$entry}_imagebf_image_photo_20240101000000_20240101000000.jpg";
        $this->pageManager->save($entry, ['bf_titre' => 'x', 'form_id' => '1', 'imagebf_image' => $entryFile], '', true, null, PageType::ENTRY);
        $this->pageManager->save($footer, [PageBody::CONTENT => implode("\n", [
            "{{attach file=\"{$newer}\"}}",
            '{{attach file="banner_R4.png"}}',
            '{{section class="cover full-width" file="bas page robustesse wiki2.jpg" height="400"}}x{{end elem="section"}}',
            "{{attach file=\"{$supporters}/soutienrwdd1.png\"}}",
        ])], '', true);
        $this->storage->write("{$this->fixtureDir}/{$supporters}_soutienrwdd1_20240101000000_20240101000000.png", 'soutien');

        $restored = $this->legacy->restoreEntryFieldFiles($this->fixtureDir, true, [$entry]);
        $report = $this->legacy->migrateUploads($this->fixtureDir);
        $rewrite = $this->legacy->rewriteReferences($this->pages);

        $this->assertSame(["{$this->fixtureDir}/{$entryFile}"], $restored);
        $this->assertSame('photo', $this->storage->read("{$this->fixtureDir}/{$entryFile}"));
        $this->assertNotNull($this->fileManager->getOne($photo));
        $this->assertCount(1, $report['created']);
        [$soutien] = $this->fileOf($supporters, 'soutienrwdd1.png');
        $this->assertSame(implode("\n", [
            "{{attach file=\"{$newer}\"}}",
            "{{attach file=\"{$newer}\"}}",
            "{{section class=\"cover full-width\" file=\"{$bas}\" height=\"400\"}}x{{end elem=\"section\"}}",
            "{{attach file=\"{$soutien}\"}}",
        ]), $this->content($footer));
        $this->assertSame([$footer], $rewrite['rewritten']);
        $this->assertNotSame($older, $newer);

        $this->assertSame([], $this->legacy->restoreEntryFieldFiles($this->fixtureDir, true, [$entry]));
        $this->assertSame([], $this->legacy->migrateUploads($this->fixtureDir)['created']);
        $this->assertSame([], $this->legacy->rewriteReferences($this->pages)['rewritten'], 'running the repair twice changes nothing');
    }

    private function page(string $prefix, string $content, string $type = PageType::PAGE): string
    {
        $tag = $prefix . uniqid();
        $body = $type === PageType::ENTRY ? ['bf_titre' => 'x', 'form_id' => '1'] : [PageBody::CONTENT => $content];
        $this->pageManager->save($tag, $body, '', true, null, $type);
        $this->pages[] = $tag;

        return $tag;
    }

    private function content(string $tag): string
    {
        $this->pageManager->forget($tag);
        $page = $this->pageManager->getOne($tag, null, false, true);

        return PageBody::content($page['body'] ?? []);
    }

    private function fileContent(string $owner, string $name, string $bytes): string
    {
        $stored = $this->fileManager->suggestFreeFilename($this->fileManager->sanitizeFilename($name));
        $this->storage->write(FileManager::STORAGE_DIR . '/' . $stored, $bytes);

        return (string)$this->fileManager->create($name, $stored, $owner, strlen($bytes), 'image/png')['tag'];
    }

    /** @return list<string> */
    private function fileOf(string $owner, string $name): array
    {
        return array_values(array_filter(
            $this->fileTagsOwnedBy([$owner]),
            fn (string $tag): bool => ($this->fileManager->getOne($tag)['original_filename'] ?? null) === $name
        ));
    }

    /**
     * @param list<string> $owners
     *
     * @return list<string>
     */
    private function fileTagsOwnedBy(array $owners): array
    {
        $tags = [];
        foreach ($this->legacy->fileIndex()['byOwner'] as $owner => $files) {
            if (in_array($owner, array_map('strtolower', $owners), true)) {
                foreach ($files as $file) {
                    $tags[] = $file['tag'];
                }
            }
        }
        sort($tags);

        return $tags;
    }
}
