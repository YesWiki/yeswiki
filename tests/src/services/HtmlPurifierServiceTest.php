<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(HtmlPurifierService::class, '__construct')]
#[CoversMethod(HtmlPurifierService::class, 'cleanHTML')]
class HtmlPurifierServiceTest extends YesWikiTestCase
{
    public function testHtmlPurifierServiceExisting(): HtmlPurifierService
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(HtmlPurifierService::class));

        return $wiki->services->get(HtmlPurifierService::class);
    }

    #[Depends('testHtmlPurifierServiceExisting')]
    #[DataProvider('dataProviderTestCleanHTML')]
    public function testCleanHTML(string $dirtyHtml, $waitedCleanedHtml, HtmlPurifierService $htmlPurifierService)
    {
        $cleanedHtml = $htmlPurifierService->cleanHTML($dirtyHtml);
        $expected = is_array($waitedCleanedHtml) ? $waitedCleanedHtml : [$waitedCleanedHtml];
        $this->assertContains($cleanedHtml, $expected, "'$dirtyHtml' was waited to be cleaned as one of [" . implode(', ', array_map(fn ($s) => "'$s'", $expected)) . "], but '$cleanedHtml' obtained");
    }

    private static function getExpectedDirtyIframeOutput(): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<div><iframe src="x">.</div>');
        $serialized = $dom->saveHTML();
        // Some libxml versions escape the dangling closing tags as text nodes
        if (str_contains($serialized, '&lt;')) {
            return 'This is a dirty iframe :<br />.&lt;/div&gt;&lt;/body&gt;&lt;/html&gt;';
        }

        return 'This is a dirty iframe :<br />.';
    }

    public static function dataProviderTestCleanHTML()
    {
        return [
            'Only text' => ['This is a test.', 'This is a test.'],
            'Text with link' => [
                'This is a <a href="https://example.com" class="btn btn-primary modalbox">link</a>.',
                'This is a <a href="https://example.com" class="btn btn-primary modalbox">link</a>.',
            ],
            'Text with link with data' => [
                'This is a <a href="https://example.com" class="btn btn-primary modalbox" data-iframe="1" data-size="modal-lg">link</a>.',
                'This is a <a href="https://example.com" class="btn btn-primary modalbox">link</a>.',
            ],
            'Text with link with target' => [
                'This is a <a href="https://example.com" class="btn btn-primary modalbox" target="_blank">link</a>.',
                'This is a <a href="https://example.com" class="btn btn-primary modalbox" target="_blank" rel="noreferrer noopener">link</a>.',
            ],
            'Text with link with not authorized target' => [
                'This is a <a href="https://example.com" class="btn btn-primary modalbox" target="blank">link</a>.',
                'This is a <a href="https://example.com" class="btn btn-primary modalbox">link</a>.',
            ],
            'Span' => ['This is a <span>word</span>.', 'This is a <span>word</span>.'],
            'Span with style : color red' => [
                'This is a <span style="color:red;">word</span>.',
                'This is a <span style="color:#FF0000;">word</span>.',
            ],
            'Span with style : color red and font size' => [
                'This is a <span style="color:red;font-size:16px;">word</span>.',
                'This is a <span style="color:#FF0000;font-size:16px;">word</span>.',
            ],
            'Span with style : color red and lang' => [
                'This is a <span style="color:red;" lang="fr">word</span>.',
                'This is a <span style="color:#FF0000;" lang="fr" xml:lang="fr">word</span>.',
            ],
            'Span with style : color red and data' => [
                'This is a <span style="color:red;" data-lang="fr">word</span>.',
                'This is a <span style="color:#FF0000;">word</span>.',
            ],
            'bold, italic, break line' => [
                'This is <b>a <br /><i>word</i></b>.',
                'This is <b>a <br /><i>word</i></b>.',
            ],
            'XSS via img' => [
                'This is an attack <img src="x" onerror="alert(\'Test !\');"/>.',
                'This is an attack <img src="x" alt="x" />.',
            ],
            'XSS via img injection' => [
                'This is an attack ><img src="x" onerror="alert(\'Test !\');"/>.',
                'This is an attack &gt;<img src="x" alt="x" />.',
            ],
            'iframe with allowed https src' => [
                'This is an iframe :<br /><iframe src="https://yeswiki.net"></iframe>',
                'This is an iframe :<br /><iframe src="https://yeswiki.net"></iframe>',
            ],
            'iframe with disallowed non-https src' => [
                // src attribute is stripped (fails URI.SafeIframeRegexp), the empty iframe tag remains
                'This is an iframe :<br /><iframe src="http://yeswiki.net"></iframe>',
                'This is an iframe :<br /><iframe></iframe>',
            ],
            'iframe with allowed embed attributes' => [
                'This is an iframe :<br /><iframe src="https://yeswiki.net" width="560" height="315" style="border:0;" title="Demo" frameborder="0" allow="fullscreen" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>',
                'This is an iframe :<br /><iframe src="https://yeswiki.net" width="560" height="315" style="border:0;" title="Demo" frameborder="0" allow="fullscreen" referrerpolicy="no-referrer-when-downgrade" allowfullscreen="allowfullscreen"></iframe>',
            ],
            'iframe with disallowed script-ish attribute' => [
                'This is an iframe :<br /><iframe src="https://yeswiki.net" onload="alert(1)"></iframe>',
                'This is an iframe :<br /><iframe src="https://yeswiki.net"></iframe>',
            ],
            'dirty iframe' => [
                // libxml serializes unclosed iframe differently depending on version — both outputs are valid
                'This is a dirty iframe :<br /><iframe src="https://yeswiki.net">.',
                [
                    'This is a dirty iframe :<br /><iframe src="https://yeswiki.net">.</iframe>',
                    'This is a dirty iframe :<br /><iframe src="https://yeswiki.net">.&lt;/div&gt;&lt;/body&gt;&lt;/html&gt;</iframe>',
                ],
            ],
        ];
    }
}
