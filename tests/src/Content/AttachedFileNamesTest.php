<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A file name is not a pattern. */
class AttachedFileNamesTest extends YesWikiTestCase
{
    private const TAG = 'AttachedFileNamesTestPage';

    /**
     * @var list<string>
     */
    private array $written = [];

    private ?string $previousTag = null;

    protected function setUp(): void
    {
        parent::setUp();

        $context = $this->getWiki()->services->get(PageContext::class);
        $this->previousTag = $context->getTag();
        $context->setTag(self::TAG);
    }

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(PageContext::class)->setTag($this->previousTag);
        foreach ($this->written as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->written = [];
        parent::tearDown();
    }

    private function paths(): AttachedFilePaths
    {
        return $this->getWiki()->services->get(AttachedFilePaths::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function awkwardNameProvider(): array
    {
        return [
            'a backslash, which PCRE reads as the start of an escape' => ['mon\\upload.jpg'],
            'an escape sequence that was never decoded' => ['photo_ann\\u00e9e.jpg'],
            'the brackets a browser adds to a duplicate' => ['photo (1).jpg'],
            'a dot in the name as well as before the extension' => ['v1.2.final.png'],
            'and the characters a pattern is made of' => ['a+b*c?.png'],
        ];
    }

    #[DataProvider('awkwardNameProvider')]
    #[WithoutErrorHandler]
    public function testAnAwkwardNameIsSearchedForRatherThanCompiled(string $file): void
    {
        $raised = [];
        set_error_handler(static function (int $errno, string $message) use (&$raised): bool {
            if (stripos($message, 'preg_') !== false || stripos($message, 'PCRE') !== false) {
                $raised[] = $message;
            }

            return true;
        });

        try {
            $found = $this->paths()->fullFilename($file);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $raised, 'the name was compiled as a pattern');
        $this->assertSame('', $found, 'there is no such file, which is not an error');
    }

    /** ...and the point of the search: an ordinary name still finds the file it names. */
    public function testAnOrdinaryNameStillFindsItsFile(): void
    {
        $paths = $this->paths();
        $directory = $paths->uploadPath();
        $encoded = $directory . '/'
            . ($paths->isSafeMode() ? $paths->currentPageTag() . '_' : '')
            . 'rapport_20260101000000_20260101000000.pdf_';
        file_put_contents($encoded, 'x');
        $this->written[] = $encoded;

        $this->assertSame($encoded, $paths->fullFilename('rapport.pdf'));
    }
}
