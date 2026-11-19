<?php

namespace YesWiki\Test\Handlers;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The widget handler writes only well-formed dimensions into its iframe.
 */
class WidgetHandlerTest extends YesWikiTestCase
{
    private array $previousGet;
    private $previousTag;

    protected function setUp(): void
    {
        $this->previousGet = $_GET;
        $this->previousTag = $this->getWiki()->tag;
        $this->getWiki()->tag = 'PagePrincipale';
    }

    protected function tearDown(): void
    {
        $_GET = $this->previousGet;
        $this->getWiki()->tag = $this->previousTag;
    }

    private function iframes(array $get): array
    {
        $_GET = $get;
        $html = $this->getWiki()->Method('widget');
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
        $frames = [];
        foreach ($dom->getElementsByTagName('iframe') as $iframe) {
            $attrs = [];
            foreach ($iframe->attributes as $attr) {
                $attrs[$attr->name] = $attr->value;
            }
            $frames[] = $attrs;
        }

        return $frames;
    }

    public static function hostileDimensions(): array
    {
        return [
            'newline then handler in width' => [['width' => "100%\n\" onload=\"alert(1)"]],
            'newline then handler in height' => [['height' => "700\n\" onload=\"alert(1)"]],
            'trailing newline' => [['width' => "100\n"]],
            'quote breakout' => [['width' => '100%" onload="alert(1)']],
            'array' => [['width' => ['100%']]],
        ];
    }

    #[DataProvider('hostileDimensions')]
    public function testAHostileDimensionFallsBackToTheDefault(array $get)
    {
        $frames = $this->iframes($get);

        $this->assertCount(1, $frames);
        $this->assertSame(['class', 'width', 'height', 'frameborder'], array_keys($frames[0]));
        $this->assertSame('100%', $frames[0]['width']);
        $this->assertSame('700', $frames[0]['height']);
    }

    public function testValidDimensionsAreKept()
    {
        $frames = $this->iframes(['width' => '640px', 'height' => '50%']);

        $this->assertSame('640px', $frames[0]['width']);
        $this->assertSame('50%', $frames[0]['height']);
    }
}
