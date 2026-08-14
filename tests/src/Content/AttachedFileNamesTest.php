<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A file name is not a pattern.
 *
 * `fullFilename()` finds an attached file by searching the upload directory for the encoded
 * name -- `name_<14 digits>_<14 digits>.ext` -- and it built that search by pasting the name
 * it was given straight into a regex. So a picture called `photo (1).jpg` searched for an
 * optional group, and a name carrying a backslash made PCRE refuse the pattern outright:
 *
 *     Warning: preg_match(): Compilation failed: PCRE2 does not support \F, \L, \l,
 *     \N{name}, \U, or \u at offset 49
 *
 * -- printed into the page being rendered, twice per card, which is how it was reported.
 *
 * The tests assert the symptom (nothing is printed, nothing is raised) and the thing that
 * quoting must not break: an ordinary name still finds its file.
 */
class AttachedFileNamesTest extends YesWikiTestCase
{
    private const TAG = 'AttachedFileNamesTestPage';

    /** @var list<string> */
    private array $written = [];

    private ?string $previousTag = null;

    protected function setUp(): void
    {
        parent::setUp();
        // an upload path and a filename prefix are both built from the page being served,
        // and a test that ran earlier may have left another one there -- `admin/preset`,
        // whose slash puts the file in a directory this would not search
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
        // only what this is about: booting a service lazily raises unrelated notices of its
        // own (a missing private/.env, Symfony deprecations), and a test that failed on
        // those would be a test about something else
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
