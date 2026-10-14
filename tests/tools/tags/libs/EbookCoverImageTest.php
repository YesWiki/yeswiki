<?php

namespace YesWiki\Test\Tags\Libs;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * An ebook cover is read only from a public URL or the wiki's files, and only if it is an image.
 */
class EbookCoverImageTest extends YesWikiTestCase
{
    private const IMAGE = 'files/EbookCoverImageTest_cover.png';
    private const NOT_AN_IMAGE = 'files/EbookCoverImageTest_cover.txt';

    protected function setUp(): void
    {
        $GLOBALS['wiki'] = $this->getWiki();
        include_once 'tools/tags/libs/tags.functions.php';
        file_put_contents(self::IMAGE, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        file_put_contents(self::NOT_AN_IMAGE, '<?php echo 1; ?>');
    }

    protected function tearDown(): void
    {
        @unlink(self::IMAGE);
        @unlink(self::NOT_AN_IMAGE);
    }

    public function testAnImageAmongTheWikiFilesIsRead()
    {
        $cover = read_ebook_cover_image(self::IMAGE);

        $this->assertSame('image/png', $cover['mime'] ?? null);
    }

    public function testAnythingElseIsRefused()
    {
        $sources = [
            'wakka.config.php',
            'files/../wakka.config.php',
            realpath('wakka.config.php'),
            'file://' . realpath('wakka.config.php'),
            'php://filter/resource=wakka.config.php',
            'data://image/png;base64,' . base64_encode('<?php echo 1; ?>'),
            'http://127.0.0.1/',
            self::NOT_AN_IMAGE,
        ];
        foreach ($sources as $source) {
            $this->assertSame([], read_ebook_cover_image($source), (string)$source);
        }
    }
}
