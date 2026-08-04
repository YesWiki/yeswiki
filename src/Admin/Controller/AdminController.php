<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `/admin/*` -- the wiki's administration, as routes rather than as pages.
 *
 * Every screen here used to be a seeded wiki page (`GererSite`, `GererConfig`,
 * `GererUtilisateurs`, ...) whose only content was one action call plus a hand-written nav
 * bar repeated in each of them. Pages are editable, renameable and deletable content, so
 * administration lived somewhere a wiki could lose; the nav bars drifted; and access
 * rested on each action re-checking `isAdmin()` for itself.
 *
 * Here the address is code, the sidebar is declared once (dashboard/layout.twig), and the
 * gate is `acl: ['@admins']` on the route -- checked by ApiService before the controller
 * is reached. The actions are unchanged and still do their own checking: a webmaster who
 * puts `{{editconfig}}` on a page of their own gets what this route gets.
 */
class AdminController extends YesWikiController
{
    use DashboardShell;

    private const ADMIN_ACL = ['@admins'];

    /**
     * The pages that make up a wiki's chrome, grouped as `GererSite` grouped them.
     *
     * Tags, not links: the template turns each into an edit URL and says whether the page
     * exists yet, so a wiki missing one gets "create" rather than a link into nothing.
     */
    private const SPECIAL_PAGES = [
        'ADMIN_SPECIAL_PAGES_CHROME' => [
            'PageTitre' => 'ADMIN_SPECIAL_PAGE_TITLE',
            'PageMenuHaut' => 'ADMIN_SPECIAL_PAGE_TOP_MENU',
            'PageRapideHaut' => 'ADMIN_SPECIAL_PAGE_QUICK_MENU',
            'PageMenu' => 'ADMIN_SPECIAL_PAGE_SIDE_MENU',
            'PageFooter' => 'ADMIN_SPECIAL_PAGE_FOOTER',
            'PageHeader' => 'ADMIN_SPECIAL_PAGE_HEADER',
        ],
        'ADMIN_SPECIAL_PAGES_CONTENT' => [
            'ReglesDeFormatage' => 'ADMIN_SPECIAL_PAGE_FORMATTING_RULES',
            'LookWiki' => 'ADMIN_SPECIAL_PAGE_LOOK',
            'PageCss' => 'ADMIN_SPECIAL_PAGE_CSS',
        ],
    ];

    #[Route('/admin', options: ['acl' => self::ADMIN_ACL])]
    public function index(): RedirectResponse
    {
        return new RedirectResponse($this->getService(UrlFormatter::class)->href('', 'admin/content'));
    }

    #[Route('/admin/content', options: ['acl' => self::ADMIN_ACL])]
    public function content(): Response
    {
        return $this->page('@core/admin/content.twig', 'admin/content');
    }

    #[Route('/admin/imports', options: ['acl' => self::ADMIN_ACL])]
    public function imports(): Response
    {
        return $this->page('@core/admin/imports.twig', 'admin/imports');
    }

    #[Route('/admin/keywords', options: ['acl' => self::ADMIN_ACL])]
    public function keywords(): Response
    {
        return $this->page('@core/admin/keywords.twig', 'admin/keywords');
    }

    #[Route('/admin/special-pages', options: ['acl' => self::ADMIN_ACL])]
    public function specialPages(): Response
    {
        $pageManager = $this->getService(PageManager::class);
        $groups = [];
        foreach (self::SPECIAL_PAGES as $groupKey => $pages) {
            $entries = [];
            foreach ($pages as $tag => $labelKey) {
                $entries[] = [
                    'tag' => $tag,
                    'label' => _t($labelKey),
                    'exists' => $pageManager->getOne($tag) !== null,
                ];
            }
            $groups[] = ['label' => _t($groupKey), 'pages' => $entries];
        }

        return $this->page('@core/admin/special-pages.twig', 'admin/special-pages', ['groups' => $groups]);
    }

    #[Route('/admin/appearance', options: ['acl' => self::ADMIN_ACL])]
    public function appearance(): Response
    {
        return $this->page('@core/admin/appearance.twig', 'admin/appearance');
    }

    #[Route('/admin/users', options: ['acl' => self::ADMIN_ACL])]
    public function users(): Response
    {
        return $this->page('@core/admin/users.twig', 'admin/users');
    }

    #[Route('/admin/groups', options: ['acl' => self::ADMIN_ACL])]
    public function groups(): Response
    {
        return $this->page('@core/admin/groups.twig', 'admin/groups');
    }

    #[Route('/admin/reactions', options: ['acl' => self::ADMIN_ACL])]
    public function reactions(): Response
    {
        return $this->page('@core/admin/reactions.twig', 'admin/reactions');
    }

    #[Route('/admin/spam', options: ['acl' => self::ADMIN_ACL])]
    public function spam(): Response
    {
        return $this->page('@core/admin/spam.twig', 'admin/spam');
    }

    #[Route('/admin/config', options: ['acl' => self::ADMIN_ACL])]
    public function config(): Response
    {
        return $this->page('@core/admin/config.twig', 'admin/config');
    }

    #[Route('/admin/updates', options: ['acl' => self::ADMIN_ACL])]
    public function updates(): Response
    {
        return $this->page('@core/admin/updates.twig', 'admin/updates');
    }

    #[Route('/admin/backups', options: ['acl' => self::ADMIN_ACL])]
    public function backups(): Response
    {
        return $this->page('@core/admin/backups.twig', 'admin/backups');
    }

    /**
     * @see DashboardController::page() -- same shell, same two shell variables.
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
