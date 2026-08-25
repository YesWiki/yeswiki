<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/** `/dashboard` -- what the wiki has been up to, and every way out of it. */
class DashboardController extends YesWikiController
{
    use DashboardShell;

    #[Route('/dashboard', options: ['acl' => ['public']])]
    public function index(): Response
    {
        return $this->page('@core/dashboard/index.twig', 'dashboard', [
            'counts' => $this->contentCounts(),
        ] + $this->exportLinks());
    }

    #[Route('/dashboard/forms', options: ['acl' => ['public']])]
    public function forms(): Response
    {
        return $this->page('@core/dashboard/forms.twig', 'dashboard/forms');
    }

    /** The lists a form's checkbox, radio and select fields draw their options from. */
    #[Route('/dashboard/lists', options: ['acl' => ['public']])]
    public function lists(): Response
    {
        return $this->page('@core/dashboard/lists.twig', 'dashboard/lists');
    }

    #[Route('/dashboard/sources', options: ['acl' => ['public']])]
    public function sources(): Response
    {
        return $this->page('@core/dashboard/sources.twig', 'dashboard/sources', [
            'sources' => $this->importedFrom(),
        ]);
    }

    /**
     * How many Contents of each type the wiki holds, in one query rather than one list per type.
     *
     * @return array<string, int>
     */
    private function contentCounts(): array
    {
        $dbService = $this->getService(DbService::class);
        $type = $dbService->quoteIdentifier('type');
        $counts = [];
        foreach ($dbService->loadAll(
            "SELECT {$type} AS content_type, COUNT(*) AS total
             FROM {$dbService->prefixTable('pages')} WHERE latest = 'Y' AND parent = '' GROUP BY {$type}"
        ) as $row) {
            $counts[(string)$row['content_type']] = (int)$row['total'];
        }

        return $counts;
    }

    /**
     * The remote wikis this one has imported Content from, grouped by origin.
     *
     * A source_url is stored per imported Content and points at the remote page it came from, so
     * the distinct values are one per entry -- 1,939 of them on a real wiki, all from one place.
     * What a reader wants is the place, so the origin is what this groups by and the entries are
     * what it counts.
     *
     * @return list<array{origin: string, total: int, lastImport: string, entries: list<array{tag: string, source: string, time: string}>}>
     */
    private function importedFrom(): array
    {
        $dbService = $this->getService(DbService::class);
        $rows = $dbService->loadAll(
            "SELECT t.resource AS tag, t.value AS source, p.{$dbService->quoteIdentifier('time')} AS time
             FROM {$dbService->prefixTable('triples')} t
             INNER JOIN {$dbService->prefixTable('pages')} p ON p.tag = t.resource AND p.latest = 'Y'
             WHERE t.property = ?
             ORDER BY t.value",
            [TripleStore::SOURCE_URL_URI]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $origin = $this->originOf((string)$row['source']);
            $grouped[$origin]['origin'] = $origin;
            $grouped[$origin]['entries'][] = [
                'tag' => (string)$row['tag'],
                'source' => (string)$row['source'],
                'time' => (string)$row['time'],
            ];
        }

        $sources = [];
        foreach ($grouped as $origin => $source) {
            $times = array_column($source['entries'], 'time');
            sort($times);
            $sources[] = [
                'origin' => $origin,
                'total' => count($source['entries']),
                'lastImport' => (string)end($times),
                'entries' => $source['entries'],
            ];
        }
        usort($sources, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $sources;
    }

    /** Where a source_url points, without the page it points at: `https://host/path`. */
    private function originOf(string $sourceUrl): string
    {
        $parts = parse_url($sourceUrl);
        if (!is_array($parts) || empty($parts['host'])) {
            return $sourceUrl;
        }
        $origin = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin . rtrim($parts['path'] ?? '', '/');
    }

    /**
     * The feeds, export formats and API links every dashboard offers.
     *
     * @return array<string, mixed>
     */
    private function exportLinks(): array
    {
        $urlFormatter = $this->getService(UrlFormatter::class);

        return [
            'feeds' => [
                [
                    'label' => _t('DASHBOARD_EXPORT_FEED_CHANGES'),
                    'url' => $urlFormatter->href('', 'DerniersChangementsRSS'),
                ],
                [
                    'label' => _t('DASHBOARD_EXPORT_FEED_ENTRIES'),
                    'url' => $urlFormatter->href('', 'api/entries/rss'),
                ],
            ],

            'formats' => [
                ['output' => 'csv', 'label' => 'CSV'],
                ['output' => 'json', 'label' => 'JSON'],
                ['output' => 'json-ld', 'label' => 'JSON-LD'],
                ['output' => 'geojson', 'label' => 'GeoJSON'],
                ['output' => 'ical', 'label' => 'iCal'],
            ],
            'forms' => $this->exportableForms(),
            'fileLinks' => [
                [
                    'icon' => 'paperclip',
                    'label' => _t('DASHBOARD_EXPORT_FILES_LIST'),
                    'url' => $urlFormatter->href('', 'api/files'),
                ],
                [
                    'icon' => 'code',
                    'label' => _t('DASHBOARD_EXPORT_API'),
                    'url' => $urlFormatter->href('', 'api'),
                ],
            ],
        ];
    }

    /**
     * Every form a reader may read, with the base URL its entries export from.
     *
     * @return list<array{label: string, baseUrl: string}>
     */
    private function exportableForms(): array
    {
        $urlFormatter = $this->getService(UrlFormatter::class);
        $forms = [];

        foreach ($this->getService(FormManager::class)->getAllLabels() as $id => $label) {
            $forms[] = [
                'label' => $label !== '' ? $label : (string)$id,
                'baseUrl' => $urlFormatter->href('', "api/forms/{$id}/entries/"),
            ];
        }

        return $forms;
    }

    /**
     * A dashboard template inside the wiki's page skeleton.
     *
     * @param array<string, mixed> $data
     */
    private function page(string $template, string $current, array $data = []): Response
    {
        $this->getService(PageContext::class)->setTag($current);

        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage($templateEngine->render($template, $this->dashboardShell($current, $data))));
    }
}
