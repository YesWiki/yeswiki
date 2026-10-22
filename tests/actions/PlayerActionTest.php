<?php

namespace YesWiki\Test\Actions;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{player}} never lets its url break out of the script or the markup it is written into.
 */
class PlayerActionTest extends YesWikiTestCase
{
    private function render(array $params): array
    {
        unset($GLOBALS['jplayer']);
        $GLOBALS['js'] = '';
        $html = $this->getWiki()->Action('player', 1, $params);

        return [$html, $GLOBALS['js']];
    }

    public function testAnAudioFileIsPlayed()
    {
        [$html, $script] = $this->render(['url' => 'https://example.org/song.mp3']);

        $this->assertStringContainsString('mp3:"https://example.org/song.mp3"', $script);
        $this->assertStringContainsString('href="https://example.org/song.mp3"', $html);
    }

    #[DataProvider('breakoutProvider')]
    public function testTheUrlCannotBreakOut(string $url)
    {
        [$html, $script] = $this->render(['url' => $url]);

        $this->assertStringNotContainsString('"});alert(1)', $script);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertDoesNotMatchRegularExpression('/href="[\\x00-\\x20]*j[\\x00-\\x20]*a[\\x00-\\x20]*v[\\x00-\\x20]*a/i', $html);
    }

    public static function breakoutProvider(): array
    {
        return [
            'script string' => ['x"});alert(1);//.mp3'],
            'markup' => ['x"><script>alert(1)</script>.mp3'],
            'markup with an unknown extension' => ['<script>alert(1)</script>'],
            'video script string' => ['x"});alert(1);//.mp4'],
            'javascript scheme' => ['javascript:alert(1)//.mp3'],
            'javascript scheme with a tab' => ["java\tscript:alert(1)//.mp3"],
        ];
    }
}
