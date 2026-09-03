<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Service\MenuManager;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LayoutService;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `/admin/menus` -- the wiki's navigation, edited where the rest of its chrome is (ticket 64).
 *
 * Beside Layout rather than beside the value lists, because a menu is chrome: it is what the site
 * looks like, and the two menus configuration names are edited on the Layout screen itself.
 */
class MenuController extends YesWikiController
{
    use DashboardShell;

    /** @var list<string> */
    private const ADMIN_ACL = ['@admins'];

    #[Route('/admin/menus', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function menus(): Response
    {
        $request = $this->getService(CurrentRequest::class)->get();
        $menus = $this->getService(MenuManager::class);

        if ($request->isMethod('POST')) {
            $this->save($request->request->getString('menu'), $request);

            return new RedirectResponse($this->getService(UrlFormatter::class)->href('', 'admin/menus'));
        }

        $layout = $this->getService(LayoutService::class);
        $chrome = array_filter([$layout->navbar(), $layout->quickMenu()]);

        $editing = $request->query->getString('menu');
        $rows = [];
        foreach ($menus->readable() as $tag => $title) {
            $rows[] = [
                'tag' => $tag,
                'title' => $title,
                'chrome' => in_array($tag, $chrome, true),
                'entries' => count($menus->getOne($tag)['nodes'] ?? []),
                'editable' => $this->getService(AclService::class)->hasAccess('write', $tag),
            ];
        }

        return $this->page('@core/admin/menus.twig', 'admin/menus', [
            'menus' => $rows,
            'editing' => $editing === '' ? null : $this->menuBeingEdited($menus, $editing),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function menuBeingEdited(MenuManager $menus, string $tag): ?array
    {
        $menu = $menus->getOne($tag);
        if ($menu === null || !$this->getService(AclService::class)->hasAccess('write', $tag)) {
            return null;
        }

        return ['tag' => $tag, 'title' => $menu['title'], 'rows' => MenuManager::rowsOf($menu['nodes'])];
    }

    /** Save one menu: the same rows every menu editor posts. */
    private function save(string $tag, Request $request): void
    {
        try {
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);

            $title = $request->request->getString('title');
            $rows = array_values(array_filter($request->request->all('entries'), 'is_array'));
            $nodes = MenuManager::nodesFromRows($rows);

            $menus = $this->getService(MenuManager::class);
            if ($tag === '') {
                $menus->create($title, $nodes);
            } else {
                $menus->update($tag, $title, $nodes);
            }
            Flash::success(_t('ADMIN_MENUS_SAVED'));
        } catch (\Throwable $failed) {
            Flash::error(_t('ADMIN_MENUS_NOT_SAVED') . ' ' . $failed->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(string $template, string $current, array $data = []): Response
    {
        $this->getService(PageContext::class)->setTag($current);
        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage(
            $templateEngine->render($template, $this->dashboardShell($current, $data))
        ));
    }
}
