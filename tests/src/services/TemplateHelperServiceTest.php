<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateHelperService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 12 (templates absorbed into core): TemplateHelperService is the renamed home of tools/templates's Utils service (a mysterious name for what is really layout-primitive/theme presentation helpers).
 */
#[CoversMethod(TemplateHelperService::class, 'checkGraphicalElements')]
class TemplateHelperServiceTest extends YesWikiTestCase
{
    public function testCheckGraphicalElementsMatchesOpenAndCloseCounts(): void
    {
        $service = $this->getWiki()->services->get(TemplateHelperService::class);

        $balanced = '{{col size="6"}}some text{{end elem="col"}}{{col size="6"}}more{{end elem="col"}}';
        $this->assertTrue($service->checkGraphicalElements('col', 'SomePage', $balanced));

        $unbalanced = '{{col size="6"}}some text{{end elem="col"}}{{col size="6"}}more';
        $this->assertFalse($service->checkGraphicalElements('col', 'SomePage', $unbalanced));

        $this->assertTrue($service->checkGraphicalElements('col', 'SomePage', null), 'no elements at all is a trivially balanced 0 == 0');

        $wrongElement = '{{panel}}some text{{end elem="col"}}';
        $this->assertFalse($service->checkGraphicalElements('panel', 'SomePage', $wrongElement));
    }

    /** A `{{tag}}` quoted in code is shown, not run, so it opens nothing that needs closing. */
    public function testCodeDoesNotCountAsAnElement(): void
    {
        $service = $this->getWiki()->services->get(TemplateHelperService::class);

        $quotedInline = "### Section `{{section}}`\n\n{{section bgcolor=\"#eee\"}}\nTexte\n{{end elem=\"section\"}}";
        $this->assertTrue($service->checkGraphicalElements('section', 'SomePage', $quotedInline));

        $quotedBlock = "```\n{{grid}}\n```\n\n{{grid}}\n{{end elem=\"grid\"}}\n\n~~~\n{{end elem=\"grid\"}}\n~~~";
        $this->assertTrue($service->checkGraphicalElements('grid', 'SomePage', $quotedBlock));

        $this->assertFalse($service->checkGraphicalElements('panel', 'SomePage', '`{{end elem="panel"}}` {{panel}}'));
    }

    /** A full address in `opengraph_image` is used as it is, with no file to look for. */
    public function testTheDefaultOpenGraphImageMayBeAUrl(): void
    {
        $services = $this->getWiki()->services;
        $service = new TemplateHelperService(
            new ParameterBag(['opengraph_image' => 'https://example.org/share.jpg']),
            $services,
            $services->get(UrlFormatter::class),
            $services->get(PerformableArguments::class)
        );

        $this->assertSame('https://example.org/share.jpg', $service->getImageFromBody(['body' => []], '1200', '630'));
    }
}
