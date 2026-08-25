<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Admin\Api\AdminLogsApiController;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\HealthService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\CustomCssService;
use YesWiki\Render\Service\CustomTemplateService;
use YesWiki\Render\Service\LayoutService;
use YesWiki\Render\Service\PresetService;
use YesWiki\Render\Service\TemplateEngine;

/** `/admin/*` -- the wiki's administration, as routes rather than as pages. */
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

    /** The Journal: what happened, and what broke (ticket 51). */
    #[Route('/admin/logs', options: ['acl' => self::ADMIN_ACL])]
    public function logs(): Response
    {
        $config = $this->getService(RuntimeConfig::class);

        return $this->page('@core/admin/logs.twig', 'admin/logs', [
            'levels' => AdminLogsApiController::levels(),
            'auditDays' => (int)($config[Journal::AUDIT_PURGE_SETTING] ?? 365),
            'diagnosticDays' => (int)($config[Journal::DIAGNOSTIC_PURGE_SETTING] ?? 14),
        ]);
    }

    /** What is wrong with this wiki right now, re-derived every time it is asked (ticket 52). */
    #[Route('/admin/health', options: ['acl' => self::ADMIN_ACL])]
    public function health(): Response
    {
        return $this->page('@core/admin/health.twig', 'admin/health', [
            'findings' => $this->getService(HealthService::class)->findings(),
        ]);
    }

    #[Route('/admin/keywords', options: ['acl' => self::ADMIN_ACL])]
    public function keywords(): Response
    {
        return $this->page('@core/admin/keywords.twig', 'admin/keywords');
    }

    /** The wiki's chrome: its brand and its two menus (ticket 30). */
    #[Route('/admin/layout', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function layout(): Response
    {
        $layout = $this->getService(LayoutService::class);
        $request = $this->getService(CurrentRequest::class)->get();

        if ($request->isMethod('POST')) {
            $this->saveLayout($layout, $request);

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

    /** The top bar as the form being filled in describes it -- previewed, not saved. */
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

    /** The wiki's colours and type: its presets (ticket 30). */
    #[Route('/admin/preset', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function preset(): Response
    {
        $presets = $this->getService(PresetService::class);
        $request = $this->getService(CurrentRequest::class)->get();

        if ($request->isMethod('POST')) {
            $this->applyPresetChange($presets, $request);

            return new RedirectResponse($this->getService(UrlFormatter::class)->href('', 'admin/preset'));
        }

        return $this->page('@core/admin/preset.twig', 'admin/preset', [
            'presets' => $presets->all(),
            'tokens' => PresetService::TOKENS,
            'groups' => PresetService::GROUPS,
            'schemes' => PresetService::SCHEMES,
            'swatches' => PresetService::SWATCHES,

            'palette' => PresetService::PALETTE,

            'rows' => PresetService::rowsByGroup(),

            'inkFor' => PresetService::INK_FOR,

            'fontStacks' => PresetService::FONT_STACKS,

            'webfonts' => $presets->webfonts(),

            'googleFontsUrl' => rtrim(
                (string)$this->getService(RuntimeConfig::class)->getValue('base_url'),
                '?'
            ) . 'src/assets/google-fonts.json',
            'defaultPreset' => $presets->default(),

            'defaultValues' => $presets->valuesFor($presets->default()),
            'configWritable' => $presets->isConfigWritable(),
            'presetsWritable' => $presets->arePresetsWritable(),
        ]);
    }

    /** Select, save or delete a preset. */
    private function applyPresetChange(PresetService $presets, SymfonyRequest $request): void
    {
        try {
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);

            switch ($request->request->get('preset_action')) {
                case 'select':
                    $selected = (string)$request->request->get('preset', '');
                    $presets->select($selected);
                    Flash::success($selected === ''
                        ? _t('ADMIN_PRESET_DESELECTED')
                        : _t('ADMIN_PRESET_SELECTED', ['name' => $presets->nameOf($selected)]));
                    break;

                case 'duplicate':
                    $copy = $presets->duplicate((string)$request->request->get('preset', ''));
                    Flash::success(_t('ADMIN_PRESET_DUPLICATED', ['name' => $presets->nameOf($copy)]));
                    break;

                case 'save':
                    $values = ['light' => [], 'dark' => []];
                    $posted = [
                        'light' => (array)$request->request->all('light'),
                        'dark' => (array)$request->request->all('dark'),
                    ];
                    foreach (PresetService::TOKENS as $token => $definition) {
                        $values['light'][$token] = trim((string)($posted['light'][$token] ?? ''));
                        if ($definition['kind'] === PresetService::KIND_COLOR) {
                            $values['dark'][$token] = trim((string)($posted['dark'][$token] ?? ''));
                        }
                    }

                    $saved = $presets->save(
                        (string)$request->request->get('preset', ''),
                        (string)$request->request->get('preset_name', ''),
                        $values
                    );

                    Flash::success(_t('ADMIN_PRESET_SAVED', ['name' => $presets->nameOf($saved)]));
                    break;

                case 'install_font':
                    $wiki = trim((string)$request->request->get('font_source', ''));
                    $family = trim((string)$request->request->get('font_family', ''));
                    if ($wiki !== '') {
                        $families = $presets->installFontsFromWiki($wiki, $family);
                        Flash::success(_t('ADMIN_PRESET_FONT_INSTALLED', [
                            'family' => implode(', ', $families),
                        ]));
                        break;
                    }

                    $result = $presets->installFonts($family);
                    if ($result['failed'] !== []) {
                        Flash::warning(_t('ADMIN_PRESET_FONT_INSTALLED_SOME', [
                            'installed' => $result['installed'] === []
                                ? '—'
                                : implode(', ', $result['installed']),
                            'failed' => implode(', ', $result['failed']),
                        ]));
                        break;
                    }
                    Flash::success(_t('ADMIN_PRESET_FONT_INSTALLED', [
                        'family' => implode(', ', $result['installed']),
                    ]));
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

    /** The wiki's own stylesheet (ticket 30). */
    #[Route('/admin/custom-css', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function customCss(): Response
    {
        $service = $this->getService(CustomCssService::class);
        $request = $this->getService(CurrentRequest::class)->get();
        $css = $service->read();

        if ($request->isMethod('POST')) {
            try {
                $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);

                $css = str_replace(["\r\n", "\r"], "\n", (string)$request->request->get('custom_css', ''));
                $service->write($css);
                Flash::success(_t('ADMIN_CUSTOM_CSS_SAVED'));
            } catch (TokenNotFoundException $invalidToken) {
                Flash::error(_t('ADMIN_CUSTOM_CSS_NOT_SAVED') . ' ' . $invalidToken->getMessage());
            } catch (\Throwable $failed) {
                Flash::error(_t('ADMIN_CUSTOM_CSS_NOT_SAVED') . ' ' . $failed->getMessage());
            }
        }

        return $this->page('@core/admin/custom-css.twig', 'admin/custom-css', [
            'css' => $css,
            'path' => $service->path(),
            'writable' => $service->isWritable(),
        ]);
    }

    /** The templates this instance overrides (ticket 30). */
    #[Route('/admin/custom-templates', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function customTemplates(): Response
    {
        $service = $this->getService(CustomTemplateService::class);
        $request = $this->getService(CurrentRequest::class)->get();

        if ($request->isMethod('POST')) {
            $editing = $this->applyCustomTemplateChange($service, $request);

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

            'original' => $shipped,
        ]);
    }

    /** Save, revert, or start an override -- three buttons on one form. */
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

            $service->write($file, str_replace(["\r\n", "\r"], "\n", (string)$request->request->get('contents', '')));
            Flash::success(_t('ADMIN_TEMPLATES_SAVED', ['name' => $file]));
        } catch (TokenNotFoundException $invalidToken) {
            Flash::error(_t('ADMIN_TEMPLATES_NOT_SAVED') . ' ' . $invalidToken->getMessage());
        } catch (\Throwable $failed) {
            Flash::error(_t('ADMIN_TEMPLATES_NOT_SAVED') . ' ' . $failed->getMessage());
        }

        return $file;
    }

    #[Route('/admin/files', options: ['acl' => self::ADMIN_ACL])]
    public function files(): Response
    {
        return $this->page('@core/admin/files.twig', 'admin/files');
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
        $this->getService(PageContext::class)->setTag($current);

        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage($templateEngine->render($template, $this->dashboardShell($current, $data))));
    }
}
