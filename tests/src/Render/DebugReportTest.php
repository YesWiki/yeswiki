<?php

namespace YesWiki\Test\Render;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\DebugReport;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/** The query log at the foot of a page in debug mode. */
class DebugReportTest extends YesWikiTestCase
{
    public function testItReportsWhatTheRequestCost(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $report = $this->reportWithDebug($wiki, true);

        $wiki->services->get(DbService::class)->loadAll('SELECT 1');

        $rendered = $report->render();
        $this->assertStringContainsString('yw-debug-report', $rendered);
        $this->assertStringContainsString('total time', $rendered);
        $this->assertStringContainsString('total SQL time', $rendered);
        $this->assertMatchesRegularExpression('/\d+ quer(y|ies)/', $rendered);

        if (!empty($wiki->services->get(ParameterBagInterface::class)->get('debug'))) {
            $this->assertStringContainsString('SELECT 1', $rendered);
        }
    }

    public function testItSaysNothingWhenDebugIsOff(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $report = $this->reportWithDebug($wiki, false);

        $this->assertSame('', $report->render(), 'a wiki in production owes nobody a query log');
        $this->assertSame(
            '<html><body>hello</body></html>',
            $report->appendTo('<html><body>hello</body></html>'),
            '...and the page comes back untouched'
        );
    }

    /** The block belongs inside the document, not after it. */
    public function testItGoesJustBeforeTheClosingBodyTag(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $report = $this->reportWithDebug($wiki, true);

        $page = $report->appendTo('<html><body><p>hello</p></body></html>');
        $this->assertStringEndsWith('</body></html>', $page);
        $this->assertLessThan(
            strripos($page, '</body>'),
            strpos($page, 'yw-debug-report'),
            'inside the body'
        );
        $this->assertGreaterThan(strpos($page, '<p>hello</p>'), strpos($page, 'yw-debug-report'), 'and last');

        $fragment = $report->appendTo('<div id="yw-main">hello</div>');
        $this->assertStringContainsString('yw-debug-report', $fragment);
    }

    /**
     * The wiki's own report, with `debug` set either way for the duration of the test -- the developer running the suite may have it on or off, and both directions matter.
     */
    private function reportWithDebug(YesWikiRuntime $wiki, bool $debug): DebugReport
    {
        $config = $wiki->services->get(RuntimeConfig::class);
        $before = $config['debug'] ?? null;
        $config['debug'] = $debug;
        $this->restoreDebug = function () use ($config, $before): void {
            $config['debug'] = $before;
        };

        return $wiki->services->get(DebugReport::class);
    }

    /**
     * @var callable|null
     */
    private $restoreDebug;

    protected function tearDown(): void
    {
        if ($this->restoreDebug !== null) {
            ($this->restoreDebug)();
            $this->restoreDebug = null;
        }
        parent::tearDown();
    }
}
