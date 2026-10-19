<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Render\Service\HtmlToMarkdown;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Remote HTML becomes Markdown that renders as the same text, and none of it can turn into wiki markup. */
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
        return $this->getWiki()->services->get(MarkdownFormatterService::class)->format($markdown);
    }

    private function text(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function testAMastodonNoteKeepsItsLinksFormatsAndLineBreak(): void
    {
        $html = $this->render($this->convert(self::MASTODON_NOTE));

        $this->assertStringContainsString('href="https://mamot.fr/@dvd"', $html);
        $this->assertStringContainsString('>@dvd</a>', $html);
        $this->assertStringContainsString('href="https://stopomarchy.org/"', $html);
        $this->assertStringContainsString('>https://stopomarchy.org/</a>', $html);
        $this->assertStringContainsString('>#Omarchy</a>', $html);
        $this->assertStringContainsString('<strong>dissolution</strong>', $html);
        $this->assertStringContainsString("<br />\nligne 2", $html);
        $this->assertSame(2, substr_count($html, '<p>'));
    }

    /** @return array<string, array{string, string}> */
    public static function markup(): array
    {
        return [
            'action' => ['{{include page="PagePrincipale"}}', '{{include page="PagePrincipale"}}'],
            'action alone on its line' => ['<p>{{redirect page="Ailleurs"}}</p>', '{{redirect page="Ailleurs"}}'],
            'comment' => ['{# caché #}', '{# caché #}'],
            'attributes' => ['texte{.rouge}', 'texte{.rouge}'],
            'raw html' => ['&lt;b&gt;x&lt;/b&gt;', '<b>x</b>'],
            'emphasis' => ['**gras** _ital_ ~~barré~~', '**gras** _ital_ ~~barré~~'],
            'heading' => ['# Titre', '# Titre'],
            'list' => ['- un', '- un'],
            'ordered list' => ['1. un', '1. un'],
            'quote and alert' => ['&gt; [!NOTE] attention', '> [!NOTE] attention'],
            'markdown link' => ['[a](https://evil.example)', '[a](https://evil.example)'],
            'autolink' => ['https://evil.example/x', 'https://evil.example/x'],
            'image' => ['![x](https://evil.example/x.png)', '![x](https://evil.example/x.png)'],
        ];
    }

    #[DataProvider('markup')]
    public function testRemoteTextCannotBecomeMarkup(string $text, string $shown): void
    {
        $html = $this->render($this->convert(str_starts_with($text, '<p>') ? $text : '<p>' . $text . '</p>'));

        $this->assertDoesNotMatchRegularExpression('#<(a|b|strong|em|del|h\d|ul|ol|li|blockquote|img|div)[\s>]#', $html);
        $this->assertSame($shown, $this->text($html));
    }

    public function testLinkTextCannotRunAnAction(): void
    {
        $html = $this->render($this->convert('<p><a href="https://example.org">{{include page="PagePrincipale"}}</a></p>'));

        $this->assertStringContainsString('{{include page="PagePrincipale"}}</a>', html_entity_decode($html));
    }

    public function testDangerousHtmlIsDropped(): void
    {
        $markdown = $this->convert('<p><a href="javascript:alert(1)">clic</a><script>alert(2)</script><img src="x" onerror="alert(3)"><img src="data:image/png;base64,AA"></p>');

        $this->assertSame('clic', $markdown);
    }

    public function testListsKeepTheirNesting(): void
    {
        $markdown = $this->convert('<ul><li>un <em>deux</em></li><li>trois<ol><li>sous</li><li>sous<ul><li>bas</li></ul></li></ol></li></ul>');

        $this->assertSame("- un *deux*\n- trois\n  1. sous\n  2. sous\n     - bas", $markdown);
        $html = $this->render($markdown);
        $this->assertMatchesRegularExpression('#<li>trois\s*<ol>\s*<li>sous</li>\s*<li>sous\s*<ul>\s*<li>bas</li>#', $html);
    }

    public function testImagesAndInlineCodeShowTheirTextOnce(): void
    {
        $html = $this->render($this->convert('<p><img src="https://example.org/a.png" alt="un @chat [x]"> et <code>{{include}} &lt;b&gt;</code></p>'));

        $this->assertStringContainsString('src="https://example.org/a.png"', $html);
        $this->assertStringContainsString('alt="un @chat (x)"', $html);
        $this->assertStringNotContainsString('&amp;#', $html);
        $this->assertStringContainsString('<code>{{include}} &lt;b&gt;</code>', $html);
    }

    public function testATrailingLineBreakLeavesNoBackslash(): void
    {
        $this->assertSame('fin', $this->convert('<p>fin<br></p>'));
    }

    public function testEmptyInputGivesEmptyText(): void
    {
        $this->assertSame('', $this->convert(''));
        $this->assertSame('', $this->convert('   '));
    }
}
