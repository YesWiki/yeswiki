<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A rich text field shown in a table cell reads as its text, not as its markup. */
class HtmlToTextFilterTest extends YesWikiTestCase
{
    private function asText(string $html): string
    {
        return $this->getWiki()->services->get(TemplateEngine::class)
            ->renderFromString('{{ html|html_to_text }}', ['html' => $html]);
    }

    public function testTagsGoAndParagraphsBecomeLines(): void
    {
        $this->assertSame(
            "Pionnière\nde l&#039;informatique",
            $this->asText('<p>Pionnière</p><p>de l&#039;<strong>informatique</strong><br /></p>')
        );
    }

    public function testEntitiesAreReadOnceAndEscapedOnce(): void
    {
        $this->assertSame('a &amp; b &lt; c', $this->asText('<p>a &amp; b &lt; c</p>'));
    }

    public function testMarkupInTheTextCannotComeBack(): void
    {
        $this->assertSame('&lt;script&gt;', $this->asText('&lt;script&gt;'));
    }
}
