<?php

namespace YesWiki\Test\Actions;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{pdf}} embeds only http(s) documents from the wiki's own host.
 */
class PdfActionTest extends YesWikiTestCase
{
    private function render(string $url): string
    {
        $wiki = $this->getWiki();
        $previous = $wiki->request;
        $wiki->request = Request::create('http://wiki.test/');
        try {
            return $wiki->Action('pdf', 1, ['url' => sprintf($url, 'wiki.test')]);
        } finally {
            $wiki->request = $previous;
        }
    }

    public function testASameHostPdfIsEmbedded()
    {
        $output = $this->render('http://%s/files/doc.pdf');

        $this->assertStringContainsString('data-pdf="http', $output);
    }

    #[DataProvider('scriptUrlProvider')]
    public function testAScriptUrlOnTheWikiHostIsRefused(string $url)
    {
        $output = $this->render($url);

        $this->assertStringNotContainsString('data-pdf', $output);
        $this->assertStringContainsString('alert-danger', $output);
    }

    public static function scriptUrlProvider(): array
    {
        return [
            'javascript' => ['javascript://%s/%%0aalert(document.domain)'],
            'uppercase javascript' => ['JaVaScRiPt://%s/%%0aalert(1)'],
            'data' => ['data://%s/text/html,<script>alert(1)</script>'],
        ];
    }
}
