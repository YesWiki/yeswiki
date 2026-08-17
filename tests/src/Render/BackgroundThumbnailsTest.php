<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\ThemeSelectorRenderer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The theme selector's background thumbnails. */
class BackgroundThumbnailsTest extends YesWikiTestCase
{
    private const DIR = 'files/backgrounds';

    protected function setUp(): void
    {
        @mkdir(self::DIR . '/thumbs', 0o777, true);

        $image = imagecreatetruecolor(8, 6);
        $blue = imagecolorallocate($image, 10, 120, 200);
        imagefilledrectangle($image, 0, 0, 7, 5, $blue === false ? 0 : $blue);
        imagejpeg($image, self::DIR . '/phpstan-ratchet-fixture.jpg');
        imagedestroy($image);
    }

    protected function tearDown(): void
    {
        @unlink(self::DIR . '/phpstan-ratchet-fixture.jpg');
        @unlink(self::DIR . '/thumbs/phpstan-ratchet-fixture.jpg');
    }

    public function testABackgroundWithoutAThumbnailGetsOne(): void
    {
        $renderer = self::getWiki()->services->get(ThemeSelectorRenderer::class);
        $prepare = (new \ReflectionClass($renderer))->getMethod('prepareBackgrounds');

        $backgrounds = $prepare->invoke($renderer);

        $this->assertContains(
            self::DIR . '/thumbs/phpstan-ratchet-fixture.jpg',
            $backgrounds,
            'the background was not thumbnailed, so the theme selector lists nothing for it'
        );
        $this->assertFileExists(
            self::DIR . '/thumbs/phpstan-ratchet-fixture.jpg',
            'the thumbnail was announced but never written'
        );
    }

    /** Second time round it is already on disk, and must not be resized again. */
    public function testAnExistingThumbnailIsReused(): void
    {
        $renderer = self::getWiki()->services->get(ThemeSelectorRenderer::class);
        $prepare = (new \ReflectionClass($renderer))->getMethod('prepareBackgrounds');

        $prepare->invoke($renderer);
        $writtenAt = filemtime(self::DIR . '/thumbs/phpstan-ratchet-fixture.jpg');

        $backgrounds = $prepare->invoke($renderer);

        $this->assertContains(self::DIR . '/thumbs/phpstan-ratchet-fixture.jpg', $backgrounds);
        $this->assertSame(
            $writtenAt,
            filemtime(self::DIR . '/thumbs/phpstan-ratchet-fixture.jpg'),
            'the thumbnail was rewritten although it already existed'
        );
    }
}
