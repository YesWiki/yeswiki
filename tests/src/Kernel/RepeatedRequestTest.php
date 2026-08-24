<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The same request, ten times in one process, ten identical answers. */
class RepeatedRequestTest extends YesWikiTestCase
{
    private const REPEATS = 10;

    private const PAGE_TAG = 'RepeatedRequestProbe';

    /**
     * A body exercising the counters and stacks ADR-0024 lists: two mail forms (the counter that
     * used to climb), two entry lists (the ids that used to drift), and a panel inside an
     * accordion (the stack that used to be handed on dirty).
     */
    private const BODY = <<<'WIKI'
        {{contact mail="nobody@example.com"}}
        {{contact mail="nobody@example.com"}}
        {{entrylist id="1"}}
        {{entrylist id="1"}}
        {{accordion}}{{panel title="One"}}first{{end elem="panel"}}{{panel title="Two"}}second{{end elem="panel"}}{{end elem="accordion"}}
        {{toc}}
        WIKI;

    protected function setUp(): void
    {
        $wiki = self::getWiki();
        $wiki->services->get(PageManager::class)->save(
            self::PAGE_TAG,
            [PageBody::CONTENT => self::BODY],
            '',
            true
        );
        $wiki->services->get(PageContext::class)->setTag(self::PAGE_TAG);
        $wiki->services->get(PageContext::class)->assignPage(
            $wiki->services->get(PageManager::class)->getOne(self::PAGE_TAG)
        );
    }

    public function testRenderingThePageTenTimesGivesTheSameAnswerEveryTime(): void
    {
        $this->render();

        $renders = [];
        for ($i = 0; $i < self::REPEATS; $i++) {
            $renders[] = $this->render();
        }

        $renders = array_map([$this, 'withoutNonces'], $renders);

        $first = array_shift($renders);
        foreach ($renders as $index => $later) {
            $this->assertSame(
                $first,
                $later,
                'render ' . ($index + 2) . ' of ' . self::REPEATS . " differs from the first.\n"
                . 'Something this page touched kept a per-request fact between renders. Under a '
                . 'worker that is what one visitor leaves for the next (ADR-0024): a mail form '
                . 'numbered wrong, a list id that drifts, a panel closed with the wrong tag.'
            );
        }
    }

    /** The counters specifically, so a failure says which one rather than "the html differs". */
    public function testTheCountersStartFromOneOnEveryRender(): void
    {
        $this->render();
        $first = $this->render();
        $tenth = '';
        for ($i = 1; $i < self::REPEATS; $i++) {
            $tenth = $this->render();
        }

        $this->assertSame(
            $this->listIdsIn($first),
            $this->listIdsIn($tenth),
            'the entry lists are numbered from a counter that did not start again'
        );
        $this->assertSame(
            $this->mailFormNumbersIn($first),
            $this->mailFormNumbersIn($tenth),
            'the mail forms are numbered from a counter that did not start again'
        );
    }

    /** One request: the scope starts, then the page renders. */
    /** The page with the values that are *supposed* to differ taken out. */
    private function withoutNonces(string $html): string
    {
        return (string)preg_replace(
            [
                '/"antiCsrfToken":"[^"]*"/',
                '/value="[^"]*\.[\w-]{20,}[^"]*"/',
                '/\b(heading|collapse|accordion_|nav_)[0-9a-f.]+/',
                '/\n[ \t]+\n/',
            ],
            ['"antiCsrfToken":"…"', 'value="…"', '$1…', "\n\n"],
            $html
        );
    }

    private function render(): string
    {
        $wiki = self::getWiki();
        $wiki->services->get(RequestScope::class)->startNewRequest();

        return $wiki->services->get(Performer::class)->run('show', 'handler', []);
    }

    /** A French visitor after an English one still reads French (ADR-0024, ticket 06). */
    public function testALanguageIsNotInheritedFromTheVisitorBefore(): void
    {
        $wiki = self::getWiki();
        $language = $wiki->services->get(LanguageService::class);

        if (!in_array('en', $language->installedLanguages(), true)) {
            $this->markTestSkipped('this wiki ships no English catalogue to alternate with');
        }

        $key = 'AB_bazar_commons2_filter_on_date_today';

        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
        $others = $wiki->config['other_languages'] ?? null;
        unset($_GET['lang'], $_COOKIE[LanguageService::COOKIE]);
        $wiki->config['other_languages'] = 'en';

        try {
            $readings = [];
            foreach (['fr', 'en', 'fr'] as $visitor) {
                $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $visitor;
                $wiki->services->get(RequestScope::class)->startNewRequest();
                $language->loadPreferredLanguage($wiki, '');
                $readings[] = $language->translate($key);
            }
        } finally {
            if ($accept === null) {
                unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            } else {
                $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $accept;
            }
            if ($others === null) {
                unset($wiki->config['other_languages']);
            } else {
                $wiki->config['other_languages'] = $others;
            }
            $wiki->services->get(RequestScope::class)->startNewRequest();
            $language->loadPreferredLanguage($wiki, '');
        }

        $this->assertNotSame(
            $readings[0],
            $readings[1],
            'the two catalogues must actually differ on this key, or the test proves nothing'
        );
        $this->assertSame(
            $readings[0],
            $readings[2],
            'the second French visitor read "' . $readings[2] . '" where the first read "'
            . $readings[0] . '". A request inherited the language of the request before it, '
            . 'which under worker mode is every visitor after the first (ADR-0024).'
        );
    }

    /** The mechanism is only worth anything if the runtime uses it. */
    public function testTheRuntimeStartsEveryRequest(): void
    {
        $runtime = (string)file_get_contents(dirname(__DIR__, 3) . '/src/YesWikiRuntime.php');

        $this->assertStringContainsString(
            'RequestScope::class)->startNewRequest()',
            $runtime,
            'YesWikiRuntime must start a fresh request scope before serving one, or the services '
            . 'below keep a visitor\'s state for the next visitor under worker mode (ADR-0024)'
        );
    }

    /** Everything holding request state is in the scope, because the interface is the registration. */
    public function testEveryRequestScopedServiceIsInTheScope(): void
    {
        $scoped = self::getWiki()->services->get(RequestScope::class)->services();

        $this->assertNotEmpty($scoped, 'the compiler pass found nothing implementing RequestScopedState');
        $this->assertContains(\YesWiki\Content\Service\ListIndex::class, $scoped);
        $this->assertContains(\YesWiki\Contact\Service\MailFormCounter::class, $scoped);
        $this->assertContains(\YesWiki\Render\Service\GraphicalElementState::class, $scoped);
    }

    /**
     * @return list<string>
     */
    private function listIdsIn(string $html): array
    {
        preg_match_all('/id="entry-list-(\d+)"/', $html, $matches);

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private function mailFormNumbersIn(string $html): array
    {
        preg_match_all('/name="nbactionmail"[^>]*value="(\d+)"/', $html, $matches);

        return $matches[1];
    }
}
