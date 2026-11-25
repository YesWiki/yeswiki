<?php

namespace YesWiki\Test\Bazar\Field;

use YesWiki\Bazar\Field\TextareaField;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A wiki-syntax textarea is saved as written; only its raw HTML blocks are purified.
 */
class TextareaFieldWikiSanitizeTest extends YesWikiTestCase
{
    private function saved(string $text): string
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $field = new TextareaField(array_replace(array_fill(0, 20, ''), [0 => 'textelong', 1 => 'bf_contenu', 7 => 'wiki']), $wiki->services);

        return $field->formatValuesBeforeSave(['id_fiche' => 'FicheTest', 'bf_contenu' => $text])['bf_contenu'];
    }

    public function testWikiTextKeepsItsAmpersandsAndBrackets()
    {
        $text = "{{bazar id=\"17\" vue=\"saisir\" redirecturl=\"PageSuite&course=Cours&module=Module\"}}\n"
            . "[[https://example.org/?a=1&b=2 un lien]] et 3 < 4 > 2\n**gras** & //italique//";

        $this->assertSame($text, $this->saved($text));
    }

    public function testRawHtmlBlocksArePurified()
    {
        $saved = $this->saved('avant ""<b onclick="alert(1)">ok</b><script>alert(2)</script>"" après & fin');

        $this->assertStringNotContainsString('onclick', $saved);
        $this->assertStringNotContainsString('<script', $saved);
        $this->assertStringContainsString('""<b>ok</b>""', $saved);
        $this->assertStringEndsWith(' après & fin', $saved);
    }

    public function testEveryRawHtmlBlockIsPurified()
    {
        $saved = $this->saved('""<i>un</i>"" & ""<img src=x onerror=alert(1)>""');

        $this->assertStringNotContainsString('onerror', $saved);
        $this->assertStringStartsWith('""<i>un</i>"" & ""', $saved);
    }

    public function testHtmlAfterAQuotedActionParameterIsPurified()
    {
        $saved = $this->saved('{{button text=""}}""<img src=x onerror=alert(1)>""');

        $this->assertStringNotContainsString('onerror', $saved);
        $this->assertStringStartsWith('{{button text=""}}""', $saved);
    }

    public function testRenderedWikiTextRunsNoScript()
    {
        $html = $this->getWiki()->Format($this->saved('[[https://x.org ""]]""<img src=x onerror=alert(1)>"" & {{a b=""}}""<svg onload=alert(2)>""'));

        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('onload', $html);
    }
}
