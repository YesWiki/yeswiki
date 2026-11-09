<?php

namespace YesWiki\Test\Core\Action;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The opening tag written by the section action. */
class SectionActionTest extends YesWikiTestCase
{
    public function testHeightAppliesWithoutABackgroundImage()
    {
        $this->assertStringContainsString('height:300px;', $this->openingTag(['height' => '300', 'bgcolor' => '#5c2c45']));
        $this->assertStringContainsString('height:300px;', $this->openingTag(['height' => '300']));
    }

    public function testNoHeightWithoutTheParameter()
    {
        $this->assertStringNotContainsString('height:', $this->openingTag(['bgcolor' => '#5c2c45']));
    }

    public function testABorderedSectionDeclaresOneBackgroundColor()
    {
        $tag = $this->openingTag(['bgcolor' => '#5c2c45', 'pattern' => 'border-solid']);

        $this->assertSame(1, substr_count($tag, 'background-color'));
        $this->assertStringContainsString('background-color: var(--main-bg-color)', $tag);
        $this->assertStringContainsString('border-color: #5c2c45', $tag);
    }

    public function testAPlainSectionKeepsItsBackgroundColor()
    {
        $this->assertStringContainsString('background-color:#5c2c45;', $this->openingTag(['bgcolor' => '#5c2c45']));
    }

    private function openingTag(array $params): string
    {
        $html = $this->getWiki()->Format('{{section' . implode('', array_map(
            fn ($name, $value) => " {$name}=\"{$value}\"",
            array_keys($params),
            $params,
        )) . '}}contenu{{end elem="section"}}');

        $this->assertSame(1, preg_match('/<section\b[^>]*>/s', $html, $match), $html);

        return $match[0];
    }
}
