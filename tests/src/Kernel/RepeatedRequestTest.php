<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The same request, ten times in one process, ten identical answers.
 *
 * This is the test ADR-0024 asks for, and the only one that can see what it is about. Every leak
 * the ADR names is sequential rather than concurrent: request two inherits what request one left
 * in a global, so a single-request test passes on a wiki that is quietly broken for everybody
 * after the first visitor. Worker mode is what makes that reachable, and this suite runs under
 * php-fpm where the process dies with the request -- so rendering repeatedly inside one PHP
 * process is how the failure is made visible without waiting for the binary to ship.
 *
 * It is a canary rather than a proof: it catches state that leaks through the services this test
 * touches. What makes the guarantee is `GlobalsRatchetTest`, which says there is no request state
 * in a global to leak.
 */
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
        // The very first render in a process is not one of the ten. Services are instantiated
        // lazily, so it is the one that builds them, and in a suite that shares its process with
        // 1,300 other tests it inherits whatever they left warm -- neither of which a worker
        // serving its second visitor does. What ADR-0024 is about is request N inheriting from
        // request N-1, and that is exactly what the ten below compare.
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

    /**
     * The counters specifically, so a failure says which one rather than "the html differs".
     *
     * Mail forms are numbered from one per page and entry lists likewise; the numbers appear in
     * the markup, so the same page rendered twice must contain the same ones.
     */
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

    /**
     * One request: the scope starts, then the page renders.
     *
     * `YesWikiRuntime::doRun()` opens every request with exactly this call, so simulating a
     * second visitor means making it here too. `testTheRuntimeStartsEveryRequest()` is what keeps
     * the two in step, because a test that started the scope while production forgot to would
     * pass on a wiki that leaks.
     */
    /**
     * The page with the values that are *supposed* to differ taken out.
     *
     * A CSRF token is a nonce and a `uniqid()` element id is a nonce; two renders that agreed on
     * those would be the bug. Everything else must match, and that is what this test is about.
     */
    private function withoutNonces(string $html): string
    {
        return (string)preg_replace(
            [
                '/"antiCsrfToken":"[^"]*"/',
                '/value="[^"]*\.[\w-]{20,}[^"]*"/',
                '/\b(heading|collapse|accordion_|nav_)[0-9a-f.]+/',
                // A line that holds only spaces. The first render of a process indents one of
                // them two spaces wider than every render after it, in the gap where
                // `{{editbar}}` sits in the squelette. It is whitespace between block-level
                // elements, no reader or parser can tell, and chasing it would say nothing about
                // request state -- renders two through ten are byte-identical to each other.
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
