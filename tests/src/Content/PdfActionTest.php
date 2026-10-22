<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{pdf}} embeds only http(s) documents from the wiki's own host.
 */
class PdfActionTest extends YesWikiTestCase
{
    private function render(string $url): string
    {
        $services = $this->getWiki()->services;
        $request = $services->get(CurrentRequest::class)->get();
        $request->headers->remove('referer');

        return $services->get(ActionRunner::class)->action('pdf url="' . sprintf($url, str_starts_with($url, '%s/') ? $request->getSchemeAndHttpHost() : $request->getHost()) . '"');
    }

    public function testASameHostPdfIsEmbedded()
    {
        $output = $this->render('%s/files/doc.pdf');

        $this->assertStringContainsString('data-pdf="http', $output);
    }

    #[DataProvider('scriptUrlProvider')]
    public function testAScriptUrlOnTheWikiHostIsRefused(string $url)
    {
        $output = $this->render($url);

        $this->assertStringNotContainsString('data-pdf', $output);
        $this->assertStringContainsString('yw-alert--danger', $output);
    }

    public static function scriptUrlProvider(): array
    {
        return [
            'javascript' => ['javascript://%s/%%0aalert(document.domain)'],
            'uppercase javascript' => ['JaVaScRiPt://%s/%%0aalert(1)'],
            'data' => ['data://%s/text/html,alert(1)'],
        ];
    }
}
