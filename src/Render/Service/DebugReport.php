<?php

namespace YesWiki\Render\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RuntimeConfig;

/** What this request cost, at the foot of the page, in debug mode. */
class DebugReport
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /** Whether to report at all. */
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

            'sqlShare' => $totalTime > 0 ? ($sqlTime / $totalTime) * 100 : 0,
        ]);
    }

    /** The block folded into a finished page, just before `</body>`. */
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
