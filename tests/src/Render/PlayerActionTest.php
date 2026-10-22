<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{player}} never lets its url break out of the markup it is written into.
 */
class PlayerActionTest extends YesWikiTestCase
{
    private function render(string $url): string
    {
        return $this->getWiki()->services->get(ActionRunner::class)->action('player', ['url' => $url]);
    }

    public function testAnAudioFileIsPlayed()
    {
        $this->assertStringContainsString('src="https://example.org/song.mp3"', $this->render('https://example.org/song.mp3'));
    }

    #[DataProvider('breakoutProvider')]
    public function testTheUrlCannotBreakOut(string $url)
    {
        $html = $this->render($url);

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertDoesNotMatchRegularExpression('/(href|src)="[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a/i', $html);
    }

    public static function breakoutProvider(): array
    {
        return [
            'markup with an unknown extension' => ['<img src=x onerror=alert(1)>'],
            'javascript scheme' => ['javascript:alert(1)//.mp3'],
            'javascript scheme with a tab' => ["java\tscript:alert(1)//.mp4"],
        ];
    }
}
