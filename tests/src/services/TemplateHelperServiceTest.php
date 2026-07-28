<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Render\Service\TemplateHelperService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 12 (templates absorbed into core): TemplateHelperService is
 * the renamed home of tools/templates's Utils service (a mysterious name for what is really
 * layout-primitive/theme presentation helpers). checkGraphicalElements() is the one every
 * layout-primitive action (accordion, col, grid, label, panel, section, buttondropdown) and
 * the {{end}} action depend on to validate open/close pairing.
 */
#[CoversMethod(TemplateHelperService::class, 'checkGraphicalElements')]
class TemplateHelperServiceTest extends YesWikiTestCase
{
    public function testCheckGraphicalElementsMatchesOpenAndCloseCounts()
    {
        $service = $this->getWiki()->services->get(TemplateHelperService::class);

        $balanced = '{{col size="6"}}some text{{end elem="col"}}{{col size="6"}}more{{end elem="col"}}';
        $this->assertTrue($service->checkGraphicalElements('col', 'SomePage', $balanced));

        $unbalanced = '{{col size="6"}}some text{{end elem="col"}}{{col size="6"}}more';
        $this->assertFalse($service->checkGraphicalElements('col', 'SomePage', $unbalanced));

        $this->assertTrue($service->checkGraphicalElements('col', 'SomePage', null), 'no elements at all is a trivially balanced 0 == 0');

        // an {{end elem="col"}} must not count towards a differently-named element's balance
        $wrongElement = '{{panel}}some text{{end elem="col"}}';
        $this->assertFalse($service->checkGraphicalElements('panel', 'SomePage', $wrongElement));
    }
}
