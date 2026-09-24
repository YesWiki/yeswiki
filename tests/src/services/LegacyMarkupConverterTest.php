<?php

namespace YesWiki\Test\Content\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Content\Service\LegacyMarkupConverter;

require_once 'tests/YesWikiTestCase.php';

/** The converter rewrites every Doryphore page once, so each wakka rule is pinned here with no database. */
class LegacyMarkupConverterTest extends TestCase
{
    #[DataProvider('conversions')]
    public function testLegacyMarkupBecomesMarkdown(string $wakka, string $markdown): void
    {
        $this->assertSame($markdown, (new LegacyMarkupConverter())->convert($wakka));
    }

    /** @return array<string, array{string, string}> */
    public static function conversions(): array
    {
        return [
            'six equals is the top heading' => ['======Titre======', '# Titre'],
            'two equals is the smallest' => ['==Petit==', '##### Petit'],
            'a heading glued to text gets its own line' => ['avant ====Milieu==== après', "avant\n\n### Milieu\n\naprès"],
            'italic' => ['un //mot// penché', 'un *mot* penché'],
            'underline has no markdown, so html' => ['__souligné__', '<u>souligné</u>'],
            'strike' => ['@@barré@@', '~~barré~~'],
            'monospace' => ['##code##', '`code`'],
            'bold is the same' => ['**gras**', '**gras**'],
            'raw html loses its quotes' => ['""<span class="x">a</span>""', '<span class="x">a</span>'],
            'a block tag stands on its own paragraph' => ['""<div class="lead">""Texte""</div>""', "<div class=\"lead\">\n\nTexte\n\n</div>"],
            'wiki link with a label' => ['[[PagePrincipale Accueil]]', '[Accueil](PagePrincipale)'],
            'wiki link without one' => ['[[PagePrincipale]]', '[PagePrincipale](PagePrincipale)'],
            'three dashes were a line break' => ['un---deux', 'un<br>deux'],
            'four or more are a rule, away from text' => ["texte\n----\nsuite", "texte\n\n----\n\nsuite"],
            'every newline was a break' => ["ligne un\nligne deux", "ligne un\\\nligne deux"],
            'indented list' => [" - un\n  - sous\n - deux", "- un\n  - sous\n- deux"],
            'ordered list' => [" 1) un\n 2) deux", "1. un\n1. deux"],
            'text after a list does not join the last item' => [" - un\nfin", "- un\n\nfin"],
            'indentation without a marker is not code' => ['     texte décalé', 'texte décalé'],
            'code block' => ['%%(php)echo 1;%%', "```php\necho 1;\n```"],
            'a url keeps its slashes' => ['voir https://exemple.org//a//b', 'voir https://exemple.org//a//b'],
            'an action is left alone' => ['{{button text="//pas italique//" link="Page"}}', '{{button text="//pas italique//" link="Page"}}'],
            'a comment is left alone' => ['{# ======pas un titre====== #}', '{# ======pas un titre====== #}'],
            'an html block does not swallow the action under it' => [
                "\"\"<div data-tf-live=\"1\"></div><script src=\"x.js\"></script>\"\"\n{{end elem=\"section\"}}",
                "<div data-tf-live=\"1\"></div><script src=\"x.js\"></script>\n\n{{end elem=\"section\"}}",
            ],
            'a comment holding an action stands alone so it stays hidden' => [
                '{{end elem="section"}}{#""<center>""{{button text="x"}}""</center>""#}',
                "{{end elem=\"section\"}}\n\n{#\"\"<center>\"\"{{button text=\"x\"}}\"\"</center>\"\"#}",
            ],
            'a heading run over several lines keeps its first' => [
                "====Et voilà !\nsuite du texte====",
                "### Et voilà !\n\nsuite du texte",
            ],
            'italics across a list, item by item' => [
                "//- un\n- deux//",
                "- *un*\n- *deux*",
            ],
            'lines of actions get no break' => ["{{grid}}\n{{col size=\"6\"}}", "{{grid}}\n{{col size=\"6\"}}"],
        ];
    }

    /** fairetilt.co's banner: a heading inside raw divs, which CommonMark only reads between blank lines. */
    public function testAHeadingInsideRawBlocksIsReadAsAHeading(): void
    {
        $wakka = '""<left>""""<div class="lead">""' . "\n" . '======Faire Tilt======""</div>""""</left>""';

        $this->assertSame(
            "<left>\n\n<div class=\"lead\">\n\n# Faire Tilt\n\n</div>\n\n</left>",
            (new LegacyMarkupConverter())->convert($wakka)
        );
    }

    #[DataProvider('conversions')]
    public function testConvertingTwiceChangesNothing(string $wakka, string $markdown): void
    {
        $converter = new LegacyMarkupConverter();
        $once = $converter->convert($wakka);

        $this->assertSame($markdown, $once);
        $this->assertSame($once, $converter->convert($once));
    }

    public function testAScriptKeepsItsLinesExactly(): void
    {
        $script = "<script>\n  var a = 1;\n  // commentaire\n</script>";

        $this->assertSame($script, (new LegacyMarkupConverter())->convert('""' . $script . '""'));
    }
}
