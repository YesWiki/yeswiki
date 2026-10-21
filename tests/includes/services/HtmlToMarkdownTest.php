<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Core\Service\HtmlToMarkdown;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Remote HTML becomes Markdown that wakka renders as the same text, and none of it can turn into wiki markup.
 */
class HtmlToMarkdownTest extends YesWikiTestCase
{
    private const MASTODON_NOTE = '<p><span class="h-card" translate="no"><a href="https://mamot.fr/@dvd" class="u-url mention">@<span>dvd</span></a></span> Il vise une <strong>dissolution</strong> de l&#39;AN</p>'
        . '<p>Lien : <a href="https://stopomarchy.org/" target="_blank" rel="nofollow noopener"><span class="invisible">https://</span><span class="">stopomarchy.org/</span><span class="invisible"></span></a> '
        . '<a href="https://mamot.fr/tags/Omarchy" class="mention hashtag" rel="tag">#<span>Omarchy</span></a><br />ligne 2</p>';

    private function convert(string $html): string
    {
        return $this->getWiki()->services->get(HtmlToMarkdown::class)->convert($html);
    }

    private function render(string $markdown): string
    {
        return $this->getWiki()->Format($markdown, 'wakka');
    }

    public function testAMastodonNoteBecomesMarkdown(): void
    {
        $this->assertSame(
            "[@dvd](https://mamot.fr/@dvd) Il vise une **dissolution** de l'AN\n\n"
            . "Lien : [https://stopomarchy.org/](https://stopomarchy.org/) [#Omarchy](https://mamot.fr/tags/Omarchy)\nligne 2",
            $this->convert(self::MASTODON_NOTE)
        );
    }

    public function testTheMarkdownRendersAsLinksAndFormats(): void
    {
        $html = $this->render($this->convert(self::MASTODON_NOTE));

        $this->assertStringContainsString('<a href="https://mamot.fr/@dvd"', $html);
        $this->assertStringContainsString('>@dvd</a>', $html);
        $this->assertStringContainsString('<a href="https://stopomarchy.org/"', $html);
        $this->assertStringContainsString('<b>dissolution</b>', $html);
    }

    public static function wikiMarkup(): array
    {
        return [
            'action' => ['{{include page="PagePrincipale"}}', '{{include page="PagePrincipale"}}'],
            'wiki link' => ['[[PagePrincipale lien]]', '[[PagePrincipale lien]]'],
            'raw html marker' => ['""&lt;b&gt;x&lt;/b&gt;""', '""<b>x</b>""'],
            'code marker' => ['%%code%%', '%%code%%'],
            'wiki title' => ['====Titre====', '====Titre===='],
            'bold and italic markers' => ['**gras** //ital//', '**gras** //ital//'],
            'camel case page name' => ['GoToSocial', 'GoToSocial'],
            'markdown link' => ['[a](https://evil.example)', '[a](https://evil.example)'],
        ];
    }

    #[DataProvider('wikiMarkup')]
    public function testRemoteTextCannotBecomeWikiMarkup(string $text, string $shown): void
    {
        $html = $this->render($this->convert('<p>' . $text . '</p>'));

        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<h', $html);
        $this->assertSame($shown, html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function testDangerousHtmlIsDropped(): void
    {
        $markdown = $this->convert('<p><a href="javascript:alert(1)">clic</a><script>alert(2)</script><img src="x" onerror="alert(3)"><img src="data:image/png;base64,AA"></p>');

        $this->assertSame('clic', $markdown);
    }

    public function testListsKeepTheirNesting(): void
    {
        $markdown = $this->convert('<ul><li>un <em>deux</em></li><li>trois<ul><li>sous</li></ul></li></ul><ol><li>a</li><li>b</li></ol>');

        $this->assertSame("  - un *deux*\n  - trois\n    - sous\n\n  1) a\n  2) b", $markdown);
        $html = $this->render($markdown);
        $this->assertStringContainsString("<li> trois\n<ul>\n<li> sous</li>", $html);
        $this->assertStringContainsString('<ol', $html);
    }

    public function testImagesAndInlineCodeShowTheirTextOnce(): void
    {
        $html = $this->render($this->convert('<p><img src="https://example.org/a.png" alt="un @chat [x]"> et <code>{{include}} &lt;b&gt;</code></p>'));

        $this->assertStringContainsString('src="https://example.org/a.png"', $html);
        $this->assertStringContainsString('alt="un @chat (x)"', $html);
        $this->assertStringNotContainsString('&amp;#', $html);
        $this->assertStringContainsString('{{include}}', html_entity_decode($html));
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function testEmptyInputGivesEmptyText(): void
    {
        $this->assertSame('', $this->convert(''));
        $this->assertSame('', $this->convert('   '));
    }
}
