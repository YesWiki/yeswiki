<?php

namespace YesWiki\Test\Render;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\DebugReport;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * The query log at the foot of a page in debug mode.
 *
 * `DbService` records every query and its duration whenever `debug` is on, and has all
 * along -- but what *printed* the log went out with the old `FooterAction`, so
 * `getQueryLog()` sat there with no caller and the block simply stopped appearing. Nothing
 * failed; a developer just stopped being told anything. That is the kind of loss a test
 * catches and a passing suite does not.
 */
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

        // The queries themselves, not just the count -- but only when the wiki running the
        // suite really has `debug` on: DbService decides whether to record a log at all,
        // and it reads the *real* config, not the one forced above. Asserting them
        // unconditionally would be asserting the developer's yeswiki.config.php.
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

    /**
     * The block belongs inside the document, not after it.
     */
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

        // a fragment with no </body> of its own -- a boosted navigation -- still gets it
        $fragment = $report->appendTo('<div id="yw-main">hello</div>');
        $this->assertStringContainsString('yw-debug-report', $fragment);
    }

    /**
     * The wiki's own report, with `debug` set either way for the duration of the test --
     * the developer running the suite may have it on or off, and both directions matter.
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

    /** @var callable|null */
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
