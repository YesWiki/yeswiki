<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
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
        $current = $services->get(CurrentRequest::class);
        $previous = $current->get();
        $current->replace(Request::create('http://wiki.test/'));
        try {
            return $services->get(ActionRunner::class)->action('pdf url="' . sprintf($url, 'wiki.test') . '"');
        } finally {
            $current->replace($previous);
        }
    }

    public function testASameHostPdfIsEmbedded(): void
    {
        $output = $this->render('http://%s/files/doc.pdf');

        $this->assertStringContainsString('data-pdf="http', $output);
    }

    #[DataProvider('scriptUrlProvider')]
    public function testAScriptUrlOnTheWikiHostIsRefused(string $url): void
    {
        $output = $this->render($url);

        $this->assertStringNotContainsString('data-pdf', $output);
        $this->assertStringContainsString('yw-alert--danger', $output);
    }

    /** @return array<string, array{0: string}> */
    public static function scriptUrlProvider(): array
    {
        return [
            'javascript' => ['javascript://%s/%%0aalert(document.domain)'],
            'uppercase javascript' => ['JaVaScRiPt://%s/%%0aalert(1)'],
            'data' => ['data://%s/text/html,alert(1)'],
        ];
    }
}
