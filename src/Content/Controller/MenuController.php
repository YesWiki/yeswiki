<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Service\MenuManager;
use YesWiki\Content\Service\TranslatableContent;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LanguageSwitch;
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
        $translatable = $this->getService(TranslatableContent::class);
        $source = $translatable->wikiLanguage();
        $writing = $translatable->editingLanguage($request->query->get('editlang'), $source);

        if ($request->isMethod('POST')) {
            $saved = $request->request->getString('menu');
            $this->save($saved, $request, $writing, $source);
            $back = $saved !== '' && $writing !== $source ? ['menu' => $saved, 'editlang' => $writing] : [];

            return new RedirectResponse($this->getService(UrlFormatter::class)->href('', 'admin/menus', $back, false));
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

        $stored = $editing === '' ? null : $menus->getUntranslated($editing);
        $open = $stored === null ? null : $this->menuBeingEdited($editing, $stored, $writing, $source);

        if ($open !== null) {
            $this->getService(LanguageSwitch::class)->writing(
                $translatable->editingLanguages($source, $writing, $stored, $translatable->menuPaths($stored))
            );
        }

        return $this->page('@core/admin/menus.twig', 'admin/menus', [
            'menus' => $rows,
            'editing' => $open,
            'editLanguage' => $writing,
            'editingTranslation' => $open !== null && $writing !== $source,
        ]);
    }

    /**
     * The menu open in the editor, in the language being written.
     *
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>|null
     */
    private function menuBeingEdited(string $tag, array $stored, string $writing, string $source): ?array
    {
        if (!$this->getService(AclService::class)->hasAccess('write', $tag)) {
            return null;
        }

        $rows = MenuManager::rowsOf(MenuManager::nodesOf($stored));
        if ($writing === $source) {
            return ['tag' => $tag, 'title' => (string)$stored['title'], 'rows' => $rows];
        }

        $written = Translations::of($stored, $writing);

        return [
            'tag' => $tag,
            'title' => (string)($written['title'] ?? ''),
            'sourceTitle' => (string)$stored['title'],
            'rows' => self::rowsForTranslating($rows, $written),
        ];
    }

    /**
     * Each row blank where its translation is, with the source wording beside it: an empty translation is what makes a reader fall back to the source.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<string, string>      $written
     *
     * @return list<array<string, mixed>>
     */
    private static function rowsForTranslating(array $rows, array $written): array
    {
        $parent = '';
        foreach ($rows as $index => $row) {
            $id = (string)($row['id'] ?? '');
            if (empty($row['child']) || $parent === '') {
                $parent = $id;
                $path = "nodes.{$id}.label";
            } else {
                $path = "nodes.{$parent}.children.{$id}.label";
            }
            $rows[$index]['sourceLabel'] = (string)($row['label'] ?? '');
            $rows[$index]['label'] = (string)($written[$path] ?? '');
        }

        return $rows;
    }

    /** Save one menu: the same rows every menu editor posts, as the source wording or as a translation of it. */
    private function save(string $tag, Request $request, string $writing, string $source): void
    {
        try {
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);

            $title = $request->request->getString('title');
            $rows = array_values(array_filter($request->request->all('entries'), 'is_array'));
            $menus = $this->getService(MenuManager::class);

            if ($tag !== '' && $writing !== $source) {
                $translatable = $this->getService(TranslatableContent::class);
                $stored = $menus->getUntranslated($tag) ?? [];
                $menus->saveTranslations($tag, $writing, $translatable->sanitize(
                    self::postedTranslations($title, $rows),
                    $translatable->menuPaths($stored)
                ));
                Flash::success(_t('ADMIN_MENUS_SAVED'));

                return;
            }

            $nodes = MenuManager::nodesFromRows($rows);
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
     * The editor's post, as the paths a menu's translations are addressed by.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, string>
     */
    private static function postedTranslations(string $title, array $rows): array
    {
        $values = ['title' => $title];
        $parent = '';
        foreach ($rows as $row) {
            $id = (string)($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if (empty($row['child']) || $parent === '') {
                $parent = $id;
                $values["nodes.{$id}.label"] = (string)($row['label'] ?? '');
            } else {
                $values["nodes.{$parent}.children.{$id}.label"] = (string)($row['label'] ?? '');
            }
        }

        return $values;
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
