<?php

namespace YesWiki\Render\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * What this request cost, at the foot of the page, in debug mode.
 *
 * `DbService` has recorded every query and its duration whenever `debug` is on for as long
 * as anyone can remember -- but the thing that *printed* the log went out with the old
 * `FooterAction` and nothing replaced it, so `getQueryLog()` has been a method with no
 * caller and the block simply stopped appearing. The `.debug` styling in `yw-core.css`
 * outlived it, which is how long it has been missing.
 *
 * Read the numbers as what they are: total time is measured from `T_START`, which is set in
 * `src/constants.php` at the very top of the request, so it covers the whole thing up to
 * the moment the page is assembled -- everything except sending it.
 *
 * In `Render` rather than in `Kernel`, where the timing and the query log come from,
 * because what it does with them is produce markup -- and Kernel may not depend on a
 * feature module (ArchitectureTest).
 */
class DebugReport
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        // lazily: this is asked for while a page is being assembled, and TemplateEngine is
        // what is doing the assembling -- injecting it here is a cycle
        $this->container = $container;
    }

    /**
     * Whether to report at all.
     *
     * `RuntimeConfig`, not the container's parameter bag: the bag is compiled and frozen,
     * while RuntimeConfig is bound by reference to the running config, so `debug` can be
     * turned off mid-request -- which is how a test renders a page without its own INSERTs
     * being dumped into the assertion (TagsWidgetTest has done exactly that since before
     * this block went missing). DbService reads the bag to decide whether to *record*; the
     * worst that can differ is a log nobody prints.
     */
    public function isEnabled(): bool
    {
        return !empty($this->container->get(RuntimeConfig::class)->getValue('debug'));
    }

    /** The block, or '' when debug is off. */
    public function render(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $queries = array_map(
            // a query built with a heredoc arrives with its indentation in it, so the log
            // was four wrapped lines per statement and unreadable at a glance. The text is
            // not shortened -- a developer wants the whole query -- only put on one line.
            fn (array $query) => ['time' => $query['time'], 'query' => trim((string)preg_replace('/\s+/', ' ', (string)$query['query']))],
            $this->container->get(DbService::class)->getQueryLog()
        );
        $sqlTime = array_sum(array_column($queries, 'time'));
        $totalTime = microtime(true) - (defined('T_START') ? T_START : microtime(true));

        return $this->container->get(TemplateEngine::class)->renderSafely('@core/debug-report.twig', [
            'queries' => $queries,
            'queryCount' => count($queries),
            'sqlTime' => $sqlTime,
            'totalTime' => $totalTime,
            // what share of the request the database accounted for -- the number that says
            // whether a slow page is slow because of SQL or in spite of it
            'sqlShare' => $totalTime > 0 ? ($sqlTime / $totalTime) * 100 : 0,
        ]);
    }

    /**
     * The block folded into a finished page, just before `</body>`.
     *
     * Inserted rather than appended: after `</html>` is not in the document, and a browser
     * moves it back into the body anyway -- so it may as well be put where it belongs.
     */
    public function appendTo(string $page): string
    {
        $report = $this->render();
        if ($report === '') {
            return $page;
        }

        $at = strripos($page, '</body>');

        return $at === false
            ? $page . $report
            : substr($page, 0, $at) . $report . substr($page, $at);
    }
}
