<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/** `/dashboard` -- what the wiki has been up to, and every way out of it. */
class DashboardController extends YesWikiController
{
    use DashboardShell;

    #[Route('/dashboard', options: ['acl' => ['public']])]
    public function index(): RedirectResponse
    {
        return new RedirectResponse($this->getService(UrlFormatter::class)->href('', 'dashboard/activity'));
    }

    #[Route('/dashboard/activity', options: ['acl' => ['public']])]
    public function activity(): Response
    {
        return $this->page('@core/dashboard/activity.twig', 'dashboard/activity');
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

    #[Route('/dashboard/export', options: ['acl' => ['public']])]
    public function export(): Response
    {
        $urlFormatter = $this->getService(UrlFormatter::class);
        $home = $this->getService(RuntimeConfig::class)['root_page'];

        return $this->page('@core/dashboard/export.twig', 'dashboard/export', [
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
        ]);
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
