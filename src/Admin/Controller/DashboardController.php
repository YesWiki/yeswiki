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

/**
 * `/dashboard` -- what the wiki has been up to, and every way out of it.
 *
 * Routes rather than pages. The wrench menu used to point at `TableauDeBord`, `GererSite`
 * and friends: wiki pages carrying actions, which every install could edit, rename or
 * delete, and which therefore could not be relied on to exist. A route is code, so it is
 * always there, always spelled the same, and its ACL is declared next to it.
 *
 * These three are public because everything they show is already public: the same
 * `{{recentchanges}}`, `{{tagcloud}}` and RSS a webmaster can put on any page. The admin
 * half lives in AdminController, behind `@admins`.
 */
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

    #[Route('/dashboard/export', options: ['acl' => ['public']])]
    public function export(): Response
    {
        $urlFormatter = $this->getService(UrlFormatter::class);
        $home = $this->getService(RuntimeConfig::class)['root_page'];

        return $this->page('@core/dashboard/export.twig', 'dashboard/export', [
            'feeds' => [
                [
                    'label' => _t('DASHBOARD_EXPORT_FEED_CHANGES'),
                    'url' => $urlFormatter->href('rss', $home),
                ],
                [
                    'label' => _t('DASHBOARD_EXPORT_FEED_COMMENTS'),
                    'url' => $urlFormatter->href('rss', $home, 'comments=1'),
                ],
            ],
            // one row per form, one column per output the entries API can answer with
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
        // getAll() is already keyed by a numeric form id and filtered to real forms
        foreach ($this->getService(FormManager::class)->getAll() as $id => $form) {
            $forms[] = [
                'label' => (string)($form['label'] ?? $form['bn_label_nature'] ?? $id),
                'baseUrl' => $urlFormatter->href('', "api/forms/{$id}/entries/"),
            ];
        }

        return $forms;
    }

    /**
     * A dashboard template inside the wiki's page skeleton.
     *
     * What the shared sidebar needs -- which entry is current, and which sections apply to
     * this reader -- comes from DashboardShell, so every screen answers it the same way.
     *
     * @param array<string, mixed> $data
     */
    private function page(string $template, string $current, array $data = []): Response
    {
        // The URL parser splits `?dashboard/forms` into tag `dashboard` + method `forms`
        // (YesWikiInit), and an action linking to "this page" asks PageContext for the tag
        // -- so BazaR's own links came out as `?dashboard&view=saisir…`, dropping the half
        // of the address that says which screen this is. The route knows its whole path.
        $this->getService(PageContext::class)->setTag($current);

        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage($templateEngine->render($template, $this->dashboardShell($current, $data))));
    }
}
