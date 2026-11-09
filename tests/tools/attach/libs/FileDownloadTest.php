<?php

namespace YesWiki\Test\Attach\Libs;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The download handler serves files by name, only those of pages the visitor can read.
 */
class FileDownloadTest extends YesWikiTestCase
{
    private const PUBLIC_TAG = 'FileDownloadTestPublic';
    private const PRIVATE_TAG = 'FileDownloadTestPrivate';
    private const PRIVATE_UNDERSCORE_TAG = 'FileDownloadTest_Hidden';
    private const STAMP = '20260101000000_20260101000000';

    private $wiki;
    private $attach;
    private $created = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $pageManager = $this->wiki->services->get(PageManager::class);
        $aclService = $this->wiki->services->get(AclService::class);
        $pageManager->save(self::PUBLIC_TAG, 'public', '', true);
        $aclService->save(self::PUBLIC_TAG, 'read', '*');
        foreach ([self::PRIVATE_TAG, self::PRIVATE_UNDERSCORE_TAG] as $tag) {
            $pageManager->save($tag, 'private', '', true);
            $aclService->save($tag, 'read', '@admins');
        }
        $this->wiki->tag = self::PUBLIC_TAG;
        $this->wiki->page = ['tag' => self::PUBLIC_TAG, 'time' => '2026-01-01 00:00:00'];

        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        $this->attach = new \attach($this->wiki);
    }

    protected function tearDown(): void
    {
        array_map('unlink', $this->created);
        $this->created = [];
        $pageManager = $this->wiki->services->get(PageManager::class);
        $aclService = $this->wiki->services->get(AclService::class);
        foreach ([self::PUBLIC_TAG, self::PRIVATE_TAG, self::PRIVATE_UNDERSCORE_TAG] as $tag) {
            $aclService->delete($tag);
            $pageManager->deleteOrphaned($tag);
        }
    }

    private function stored(string $name): string
    {
        $path = $this->attach->GetUploadPath() . '/' . $name;
        file_put_contents($path, 'content');
        $this->created[] = $path;

        return $path;
    }

    public static function servedNames(): array
    {
        return [
            'accents' => ['été'],
            'apostrophe' => ["l'ami"],
            'ampersand' => ['a&b'],
            'double quote' => ['a"b'],
            'parentheses' => ['rapport_(1)'],
        ];
    }

    #[DataProvider('servedNames')]
    public function testAFileOfThePageIsFoundByItsShortOrLongName(string $name)
    {
        $path = $this->stored(self::PUBLIC_TAG . "_{$name}_" . self::STAMP . '.pdf_');

        $this->assertSame($path, $this->attach->resolveDownload("$name.pdf"));
        $this->assertSame($path, $this->attach->resolveDownload(basename($path)));
    }

    public function testAMissingFileResolvesToNothing()
    {
        $this->assertNull($this->attach->resolveDownload('absent.pdf'));
        $this->assertNull($this->attach->resolveDownload('absent'));
        $this->assertNull($this->attach->resolveDownload('..'));
        $this->assertNull($this->attach->resolveDownload(''));
    }

    public function testARequestThatIsNotAStringResolvesToNothing()
    {
        $this->assertNull($this->attach->resolveDownload(['x']));
        $this->assertNull($this->attach->resolveDownload(null));
    }

    public function testAFileNameIsNotARegularExpression()
    {
        $this->stored(self::PUBLIC_TAG . '_doc_' . self::STAMP . '.pdf_');

        $this->assertNull($this->attach->resolveDownload('.*.pdf'));
        $this->assertNull($this->attach->resolveDownload('do..pdf'));
        $this->assertNull($this->attach->resolveDownload('`(.pdf'));
    }

    public function testAFileOfAPageTheVisitorCannotReadIsRefused()
    {
        $path = $this->stored(self::PRIVATE_TAG . '_secret_' . self::STAMP . '.pdf_');

        $this->assertNull($this->attach->resolveDownload(self::PRIVATE_TAG . '/secret.pdf'));
        $this->assertNull($this->attach->resolveDownload(basename($path)));
        $this->assertNull($this->attach->resolveDownload('FileDownloadTest.*/.*.pdf'));
    }

    public function testAPrivatePageWhoseTagHasAnUnderscoreIsProtectedToo()
    {
        $path = $this->stored(self::PRIVATE_UNDERSCORE_TAG . '_secret_' . self::STAMP . '.pdf_');

        $this->assertNull($this->attach->resolveDownload(basename($path)));
    }

    public function testAFileOfAnotherReadablePageIsServed()
    {
        $this->wiki->services->get(AclService::class)->save(self::PRIVATE_TAG, 'read', '*');
        $path = $this->stored(self::PRIVATE_TAG . '_open_' . self::STAMP . '.pdf_');

        $this->assertSame($path, $this->attach->resolveDownload(self::PRIVATE_TAG . '/open.pdf'));
    }

    public function testATrashedFileIsRefusedToAnAnonymousVisitor()
    {
        $path = $this->stored(self::PUBLIC_TAG . '_old_' . self::STAMP . '.pdf_trash20260102000000');

        $this->assertNull($this->attach->resolveDownload(basename($path)));
    }

    public function testTheDownloadNameIsCleanAndHeaderSafe()
    {
        $this->assertSame(
            "attachment; filename=_t_.pdf; filename*=utf-8''%C3%A9t%C3%A9.pdf",
            $this->attach->downloadDisposition('files/' . self::PUBLIC_TAG . '_été_' . self::STAMP . '.pdf_', false)
        );
        $this->assertStringNotContainsString(
            'a"b',
            $this->attach->downloadDisposition('files/' . self::PUBLIC_TAG . '_a"b_' . self::STAMP . '.pdf_', false)
        );
    }
}
