<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\CustomCssService;
use YesWiki\Render\Service\CustomTemplateService;
use YesWiki\Render\Service\LayoutService;
use YesWiki\Render\Service\PresetService;
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

    /**
     * The wiki's chrome: its brand and its two menus (ticket 30).
     *
     * What this screen edits used to be three wiki pages -- `PageTitre`, `PageMenuHaut`,
     * `PageRapideHaut` -- and the only way to edit a menu was to edit a markdown list in a
     * text box. They are configuration now (LayoutService), so this is fields.
     *
     * The three chrome pages that are *still* pages are listed at the bottom with links to
     * their editors: the banner, the side menu and the footer hold arbitrary wiki content,
     * and this screen's job for them is being the place you remember they exist.
     */
    #[Route('/admin/layout', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function layout(): Response
    {
        $layout = $this->getService(LayoutService::class);
        $request = $this->getService(CurrentRequest::class)->get();

        if ($request->isMethod('POST')) {
            $this->saveLayout($layout, $request);

            // redirected rather than re-rendered: the chrome around this very page is what
            // was just changed, and this response was built before it
            return new RedirectResponse($this->getService(UrlFormatter::class)->href('', 'admin/layout'));
        }

        $pageManager = $this->getService(PageManager::class);
        $pages = [];
        foreach (LayoutService::PAGES as $tag) {
            $pages[] = [
                'tag' => $tag,
                'label' => _t('ADMIN_LAYOUT_PAGE_' . strtoupper(substr($tag, 4))),
                'exists' => $pageManager->getOne($tag) !== null,
            ];
        }

        // flattened here rather than in the template: the form posts a flat list of rows, so
        // the screen edits the same shape it saves, and Twig is spared counting an index
        // across a nested loop
        $navbarRows = [];
        foreach ($layout->navbar() as $entry) {
            $navbarRows[] = ['label' => $entry['label'], 'link' => $entry['link'], 'child' => false];
            foreach ($entry['children'] as $child) {
                $navbarRows[] = ['label' => $child['label'], 'link' => $child['link'], 'child' => true];
            }
        }

        return $this->page('@core/admin/layout.twig', 'admin/layout', [
            'title' => $layout->ownTitle(),
            'fallbackTitle' => $layout->title(),
            'logo' => $layout->logo(),
            'brandModes' => LayoutService::BRAND_MODES,
            'brandMode' => $layout->brandMode(),
            'navbarRows' => $navbarRows,
            'quickMenu' => $layout->quickMenu(),
            'accountButton' => $layout->hasAccountButton(),
            'navbarHeight' => $layout->navbarHeight(),
            'navbarHeightMin' => LayoutService::NAVBAR_HEIGHT_MIN,
            'navbarHeightMax' => LayoutService::NAVBAR_HEIGHT_MAX,
            'pages' => $pages,
            'configWritable' => $layout->isConfigWritable(),
        ]);
    }

    /**
     * The top bar as the form being filled in describes it -- previewed, not saved.
     *
     * The screen renders *inside* the chrome it edits, so the honest preview is the real
     * navbar at the top of this very page. htmx posts the form here on every change and swaps
     * the answer into `#yw-layout-chrome`.
     *
     * Preview rather than live saving, and that is the decision: saving rewrites
     * `yeswiki.config.php`, which invalidates the compiled container -- a rebuild paid by
     * every visitor of the wiki, not by the person editing -- and it would put a half-typed
     * menu entry on the public site with no undo. Same split the Preset screen already draws
     * between trying a preset on this page and making it the wiki's.
     *
     * Nothing is written here, so the only thing this route can leak is what the person
     * posting it just typed.
     */
    #[Route('/admin/layout/preview', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function layoutPreview(): Response
    {
        $request = $this->getService(CurrentRequest::class)->get();
        [$brand, $navbar, $quickMenu] = $this->readLayoutForm($request);

        $chrome = $this->getService(LayoutService::class)->fromForm($brand, $navbar, $quickMenu);

        return new Response($this->getService(TemplateEngine::class)->renderLayoutChrome($chrome));
    }

    /**
     * The three structures the Layout form describes, without deciding what to do with them.
     *
     * Shared by the save and the preview so that what is previewed is what gets written --
     * two readings of one form is two chances for them to disagree.
     *
     * The navbar arrives as a flat list of rows carrying a `child` flag rather than as a
     * tree: a form posts a flat list, and "indent this row under the one above" is both how
     * the screen edits it and how a nested list reads. A child row with nothing above it is
     * kept as a top-level entry rather than dropped -- the row exists because someone typed
     * in it.
     *
     * @return array{0: array<string, mixed>, 1: list<array{label: string, link: string, children: list<array{label: string, link: string}>}>, 2: list<array{icon: string, label: string, link: string}>}
     */
    private function readLayoutForm(SymfonyRequest $request): array
    {
        $navbar = [];
        foreach ($request->request->all('navbar') as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = (string)($row['label'] ?? '');
            $link = (string)($row['link'] ?? '');
            $parent = array_key_last($navbar);
            if (!empty($row['child']) && $parent !== null) {
                $navbar[$parent]['children'][] = ['label' => $label, 'link' => $link];
                continue;
            }
            $navbar[] = ['label' => $label, 'link' => $link, 'children' => []];
        }

        $quickMenu = [];
        foreach ($request->request->all('quick') as $row) {
            if (is_array($row)) {
                $quickMenu[] = [
                    'icon' => (string)($row['icon'] ?? ''),
                    'label' => (string)($row['label'] ?? ''),
                    'link' => (string)($row['link'] ?? ''),
                ];
            }
        }

        $brand = [
            'title' => (string)$request->request->get('layout_title', ''),
            'logo' => (string)$request->request->get('layout_logo', ''),
            'brand' => (string)$request->request->get('layout_brand', 'text'),
            'account' => $request->request->has('layout_account_button'),
            'height' => $request->request->get('layout_navbar_height'),
        ];

        return [$brand, $navbar, $quickMenu];
    }

    /** Read the whole form back into configuration -- the same reading the preview does. */
    private function saveLayout(LayoutService $layout, SymfonyRequest $request): void
    {
        try {
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);

            [$brand, $navbar, $quickMenu] = $this->readLayoutForm($request);
            $layout->save($brand, $navbar, $quickMenu);

            Flash::success(_t('ADMIN_LAYOUT_SAVED'));
        } catch (TokenNotFoundException $invalidToken) {
            Flash::error(_t('ADMIN_LAYOUT_NOT_SAVED') . ' ' . $invalidToken->getMessage());
        } catch (\Throwable $failed) {
            Flash::error(_t('ADMIN_LAYOUT_NOT_SAVED') . ' ' . $failed->getMessage());
        }
    }

    /**
     * The wiki's colours and type: its presets (ticket 30).
     *
     * Presets only. The theme/squelette/style selector this screen briefly carried is going
     * to the configuration file, and per-page themes are a property of the content, so both
     * left -- what remains is one list, and a rail to edit what is in it.
     *
     * The component gallery below the list is the point of the screen: a preset is judged
     * against real buttons, panels and headings, because a contrast that works on a
     * paragraph can fail on a button.
     */
    #[Route('/admin/preset', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function preset(): Response
    {
        $presets = $this->getService(PresetService::class);
        $request = $this->getService(CurrentRequest::class)->get();

        if ($request->isMethod('POST')) {
            $this->applyPresetChange($presets, $request);

            // Redirected rather than re-rendered: which preset is on is decided at the very
            // start of a request, when the head is built, so this response would still be
            // wearing the one that was just replaced.
            return new RedirectResponse($this->getService(UrlFormatter::class)->href('', 'admin/preset'));
        }

        return $this->page('@core/admin/preset.twig', 'admin/preset', [
            'presets' => $presets->all(),
            'variables' => PresetService::VARIABLES,
            'swatches' => PresetService::SWATCHES,
            'defaultPreset' => $presets->default(),
            // "new" opens on what the wiki is wearing: a preset is almost always made by
            // adjusting one that nearly works, not from black on white
            'defaultValues' => $presets->valuesFor($presets->default()),
            'configWritable' => $presets->isConfigWritable(),
            'presetsWritable' => $presets->arePresetsWritable(),
        ]);
    }

    /**
     * Select, save or delete a preset.
     *
     * One route for the three because they are three buttons on one screen; which one was
     * pressed is `preset_action`. Every failure is a flash rather than an exception page:
     * this is a colour scheme, and losing the screen over a bad name helps nobody.
     */
    private function applyPresetChange(PresetService $presets, SymfonyRequest $request): void
    {
        try {
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);

            switch ($request->request->get('preset_action')) {
                // "make this the wiki's" -- the only button here that changes what every
                // other page wears. Trying a preset out is the card itself, and never
                // leaves the browser.
                case 'select':
                    // The starred button of the preset in use posts an empty id: the star is a
                    // toggle, so taking the wiki back to its theme's own colours is the same
                    // button rather than a second one nobody would look for.
                    $selected = (string)$request->request->get('preset', '');
                    $presets->select($selected);
                    Flash::success($selected === ''
                        ? _t('ADMIN_PRESET_DESELECTED')
                        : _t('ADMIN_PRESET_SELECTED', ['name' => $presets->nameOf($selected)]));
                    break;

                case 'duplicate':
                    // named after the copy, not the original: the copy is what appeared in the
                    // list, and freeFileName() may have had to number it
                    $copy = $presets->duplicate((string)$request->request->get('preset', ''));
                    Flash::success(_t('ADMIN_PRESET_DUPLICATED', ['name' => $presets->nameOf($copy)]));
                    break;

                case 'save':
                    $values = [];
                    foreach (array_keys(PresetService::VARIABLES) as $variable) {
                        $values[$variable] = (string)$request->request->get($variable, '');
                    }
                    // the preset being edited, so a save replaces it -- renaming included.
                    // Empty when the rail was opened by "create a preset".
                    $saved = $presets->save(
                        (string)$request->request->get('preset', ''),
                        (string)$request->request->get('preset_name', ''),
                        $values
                    );
                    // the saved id's name rather than what was typed: a name is folded to a
                    // file name, so this is the one the card below will be wearing
                    Flash::success(_t('ADMIN_PRESET_SAVED', ['name' => $presets->nameOf($saved)]));
                    break;

                case 'delete':
                    $deleted = (string)$request->request->get('preset', '');
                    $presets->delete($deleted);
                    Flash::success(_t('ADMIN_PRESET_DELETED', ['name' => $presets->nameOf($deleted)]));
                    break;
            }
        } catch (TokenNotFoundException $invalidToken) {
            Flash::error(_t('ADMIN_PRESET_NOT_SAVED') . ' ' . $invalidToken->getMessage());
        } catch (\Throwable $failed) {
            Flash::error(_t('ADMIN_PRESET_NOT_SAVED') . ' ' . $failed->getMessage());
        }
    }

    /**
     * The wiki's own stylesheet (ticket 30).
     *
     * A file under `custom/styles/`, not the `PageCss` page it replaces. GET and POST share
     * the route because there is one thing on the screen: a box holding the whole file, and
     * a button that writes it.
     */
    #[Route('/admin/custom-css', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function customCss(): Response
    {
        $service = $this->getService(CustomCssService::class);
        $request = $this->getService(CurrentRequest::class)->get();
        $css = $service->read();

        if ($request->isMethod('POST')) {
            try {
                $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
                // the textarea is the file: what arrives is written verbatim, newlines
                // normalised so a Windows browser does not rewrite every line on every save
                $css = str_replace(["\r\n", "\r"], "\n", (string)$request->request->get('custom_css', ''));
                $service->write($css);
                Flash::success(_t('ADMIN_CUSTOM_CSS_SAVED'));
            } catch (TokenNotFoundException $invalidToken) {
                Flash::error(_t('ADMIN_CUSTOM_CSS_NOT_SAVED') . ' ' . $invalidToken->getMessage());
            } catch (\Throwable $failed) {
                // a stylesheet that silently did not save is the worst outcome here: the
                // page looks the same either way
                Flash::error(_t('ADMIN_CUSTOM_CSS_NOT_SAVED') . ' ' . $failed->getMessage());
            }
        }

        return $this->page('@core/admin/custom-css.twig', 'admin/custom-css', [
            'css' => $css,
            'path' => $service->path(),
            'writable' => $service->isWritable(),
        ]);
    }

    /**
     * The templates this instance overrides (ticket 30).
     *
     * A list of what is in `custom/templates/`, an editor for each, and a picker that starts
     * a new override by copying the shipped template verbatim.
     *
     * **There is no sandbox, on purpose, and it was measured rather than assumed.** Twig's
     * sandbox propagates into `{% extends %}`ed templates, so an override that extends a core
     * template cannot be sandboxed without sandboxing the core template too -- which fails on
     * its first method call. `custom/templates/` has always been on the main Twig loader, so
     * an override has always been code; the boundary is this route's `@admins`, and an admin
     * who wants to run PHP has shorter routes than a Twig file (`{{editconfig}}`, updates).
     *
     * The one safeguard that has to exist is that this screen keeps working when an override
     * does not: it renders through `@shipped`, which `custom/templates/` is not on. A broken
     * override that also broke the screen that fixes it would leave only FTP.
     */
    #[Route('/admin/custom-templates', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function customTemplates(): Response
    {
        $service = $this->getService(CustomTemplateService::class);
        $request = $this->getService(CurrentRequest::class)->get();

        if ($request->isMethod('POST')) {
            $editing = $this->applyCustomTemplateChange($service, $request);

            // redirected rather than re-rendered: this screen edits the templates the wiki
            // renders with, and this response was compiled before the change
            return new RedirectResponse($this->getService(UrlFormatter::class)->href(
                '',
                'admin/custom-templates' . ($editing === '' ? '' : '&file=' . rawurlencode($editing))
            ));
        }

        $editing = (string)$request->query->get('file', '');
        $contents = '';
        $shipped = null;
        if ($editing !== '' && $service->exists($editing)) {
            $contents = $service->read($editing);
            $shipped = $service->readShipped($editing);
        } else {
            $editing = '';
        }

        return $this->page(CustomTemplateService::SCREEN_TEMPLATE, 'admin/custom-templates', [
            'overrides' => $service->overrides(),
            'shippedTemplates' => $service->shipped(),
            'directory' => CustomTemplateService::DIRECTORY,
            'writable' => $service->isWritable(),
            'editing' => $editing,
            'contents' => $contents,
            // shown beside the box so "what did I change" is answerable without a checkout
            'original' => $shipped,
        ]);
    }

    /**
     * Save, revert, or start an override -- three buttons on one form.
     *
     * Returns the override the screen should come back to, which is '' for a revert: the
     * file it was editing no longer exists, and an editor open on a deleted file is a box
     * whose Save would recreate it.
     */
    private function applyCustomTemplateChange(CustomTemplateService $service, SymfonyRequest $request): string
    {
        $file = (string)$request->request->get('file', '');

        try {
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);

            if ($request->request->has('revert')) {
                $service->delete($file);
                Flash::success(_t('ADMIN_TEMPLATES_REVERTED', ['name' => $file]));

                return '';
            }

            if ($request->request->has('create')) {
                $file = $service->copyFromShipped((string)$request->request->get('shipped', ''));
                Flash::success(_t('ADMIN_TEMPLATES_CREATED', ['name' => $file]));

                return $file;
            }

            // newlines normalised so a Windows browser does not rewrite every line of a
            // template on every save, which would make every diff useless
            $service->write($file, str_replace(["\r\n", "\r"], "\n", (string)$request->request->get('contents', '')));
            Flash::success(_t('ADMIN_TEMPLATES_SAVED', ['name' => $file]));
        } catch (TokenNotFoundException $invalidToken) {
            Flash::error(_t('ADMIN_TEMPLATES_NOT_SAVED') . ' ' . $invalidToken->getMessage());
        } catch (\Throwable $failed) {
            // where a template that does not compile is reported: it is refused before it
            // is written, so the wiki is still rendering the last one that worked
            Flash::error(_t('ADMIN_TEMPLATES_NOT_SAVED') . ' ' . $failed->getMessage());
        }

        return $file;
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
