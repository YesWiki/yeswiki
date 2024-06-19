<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\HtmlPurifierService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class HtmlPurifierServiceTest extends YesWikiTestCase
{
    /**
     * @covers \UserManager::__construct
     *
     * @return HtmlPurifierService $htmlPurifierService
     */
    public function testHtmlPurifierServiceExisting(): HtmlPurifierService
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(HtmlPurifierService::class));

        return $wiki->services->get(HtmlPurifierService::class);
    }

    /**
     * @depends testHtmlPurifierServiceExisting
     * @covers \HtmlPurifierService::cleanHTML
     * @dataProvider dataProviderTestCleanHTML
     */
    public function testCleanHTML(string $dirtyHtml, string $waitedCleanedHtml, HtmlPurifierService $htmlPurifierService)
    {
        $cleanedHtml = $htmlPurifierService->cleanHTML($dirtyHtml);
        $this->assertEquals($cleanedHtml, $waitedCleanedHtml, "'$dirtyHtml' was waited to be cleaned as '$waitedCleanedHtml', but '$cleanedHtml' obtained");
    }

    public function dataProviderTestCleanHTML()
    {
        return [
            'Only text' => [
                'dirtyHtml' => 'This is a test.',
                'waitedCleanedHtml' => 'This is a test.',
            ],
            'Text with link' => [
                'dirtyHtml' => 'This is a <a href="https://example.com" class="btn btn-primary modalbox">link</a>.',
                'waitedCleanedHtml' => 'This is a <a href="https://example.com" class="btn btn-primary modalbox">link</a>.',
            ],
            'Text with link with data' => [
                'dirtyHtml' => 'This is a <a href="https://example.com" class="btn btn-primary modalbox" data-iframe="1" data-size="modal-lg">link</a>.',
                'waitedCleanedHtml' => 'This is a <a href="https://example.com" class="btn btn-primary modalbox">link</a>.',
            ],
            'Text with link with target' => [
                'dirtyHtml' => 'This is a <a href="https://example.com" class="btn btn-primary modalbox" target="_blank">link</a>.',
                'waitedCleanedHtml' => 'This is a <a href="https://example.com" class="btn btn-primary modalbox" target="_blank" rel="noreferrer noopener">link</a>.',
            ],
            'Text with link with not authorized target' => [
                'dirtyHtml' => 'This is a <a href="https://example.com" class="btn btn-primary modalbox" target="blank">link</a>.',
                'waitedCleanedHtml' => 'This is a <a href="https://example.com" class="btn btn-primary modalbox">link</a>.',
            ],
            'Span' => [
                'dirtyHtml' => 'This is a <span>word</span>.',
                'waitedCleanedHtml' => 'This is a <span>word</span>.',
            ],
            'Span with style : color red' => [
                'dirtyHtml' => 'This is a <span style="color:red;">word</span>.',
                'waitedCleanedHtml' => 'This is a <span style="color:#FF0000;">word</span>.',
            ],
            'Span with style : color red and font size' => [
                'dirtyHtml' => 'This is a <span style="color:red;font-size:16px;">word</span>.',
                'waitedCleanedHtml' => 'This is a <span style="color:#FF0000;font-size:16px;">word</span>.',
            ],
            'Span with style : color red and lang' => [
                'dirtyHtml' => 'This is a <span style="color:red;" lang="fr">word</span>.',
                'waitedCleanedHtml' => 'This is a <span style="color:#FF0000;" lang="fr" xml:lang="fr">word</span>.',
            ],
            'Span with style : color red and data' => [
                'dirtyHtml' => 'This is a <span style="color:red;" data-lang="fr">word</span>.',
                'waitedCleanedHtml' => 'This is a <span style="color:#FF0000;">word</span>.',
            ],
            'bold, italic, break line' => [
                'dirtyHtml' => 'This is <b>a <br /><i>word</i></b>.',
                'waitedCleanedHtml' => 'This is <b>a <br /><i>word</i></b>.',
            ],
            'XSS via img' => [
                'dirtyHtml' => 'This is an attack <img src="x" onerror="alert(\'Test !\');"/>.',
                'waitedCleanedHtml' => 'This is an attack <img src="x" alt="x" />.',
            ],
            'XSS via img injection' => [
                'dirtyHtml' => 'This is an attack ><img src="x" onerror="alert(\'Test !\');"/>.',
                'waitedCleanedHtml' => 'This is an attack &gt;<img src="x" alt="x" />.',
            ],
            'iframe' => [
                'dirtyHtml' => 'This is an iframe :<br /><iframe src="https://yeswiki.net"></iframe>',
                'waitedCleanedHtml' => 'This is an iframe :<br />',
            ],
            'dirty iframe' => [
                'dirtyHtml' => 'This is a dirty iframe :<br /><iframe src="https://yeswiki.net">.',
                'waitedCleanedHtml' => 'This is a dirty iframe :<br />.',
            ],
        ];
    }
}
