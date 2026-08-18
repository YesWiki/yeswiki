<?php

namespace YesWiki\Render\Service;

use Carbon\Carbon;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Files\Service\RemoteImageCache;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Exception\TemplateNotFound;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Entity\LayoutChrome;

class TemplateEngine
{
    protected ContainerInterface $container;
    protected $twigLoader;
    protected $twig;
    protected AssetRegistry $assetRegistry;
    protected $csrfTokenManager;

    protected UrlFormatter $urlFormatter;

    public function __construct(
        ContainerInterface $container,
        ParameterBagInterface $config,
        AssetRegistry $assetRegistry,
        CsrfTokenManager $csrfTokenManager,
        UrlFormatter $urlFormatter
    ) {
        $this->urlFormatter = $urlFormatter;
        $this->container = $container;
        $this->assetRegistry = $assetRegistry;
        $this->csrfTokenManager = $csrfTokenManager;

        $this->twigLoader = new \Twig\Loader\FilesystemLoader(['./', YESWIKI_SOURCE_DIR]);

        if (file_exists('custom/templates/')) {
            $this->twigLoader->addPath('custom/templates/', 'custom');
        }

        foreach ($this->container->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all() as $extensionName => $pluginInfo) {
            $paths = ["custom/templates/$extensionName/"];

            $paths[] = "custom/themes/tools/$extensionName/templates/";

            $paths[] = 'custom/templates/' . $extensionName . '/templates/';

            $paths[] = "custom/extensions/$extensionName/templates";

            $paths[] = YESWIKI_SOURCE_DIR . '/templates/' . $extensionName . '/templates/';
            $paths[] = YESWIKI_SOURCE_DIR . '/templates/' . $extensionName . '/';

            $paths[] = YESWIKI_SOURCE_DIR . '/themes/tools/' . $extensionName . '/templates/';
            $paths[] = YESWIKI_SOURCE_DIR . '/themes/tools/' . $extensionName . '/';

            $vFavoriteTheme = $config->get('favorite_theme');

            $paths[] = YESWIKI_SOURCE_DIR . "/themes/{$vFavoriteTheme}/tools/" . $extensionName . '/templates/';
            $paths[] = YESWIKI_SOURCE_DIR . "/themes/{$vFavoriteTheme}/tools/" . $extensionName . '/';

            foreach ($this->container->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all() as $otherExtensionName => $otherExtensionPath) {
                $paths[] = "custom/extensions/$otherExtensionName/templates/$extensionName/";
                $paths[] = $otherExtensionPath . "templates/$extensionName/";
            }

            $paths[] = YESWIKI_SOURCE_DIR . "/extensions/$extensionName/templates/";

            $paths[] = YESWIKI_SOURCE_DIR . "/extensions/$extensionName/presentation/templates/";

            foreach ($paths as $path) {
                if (file_exists($path)) {
                    $this->twigLoader->addPath($path, $extensionName);
                }
            }
        }

        $corePaths = [];
        $corePaths[] = 'custom/templates/core/';

        foreach ($this->container->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all() as $otherExtensionName => $otherExtensionPath) {
            $corePaths[] = $otherExtensionPath . 'templates/core/';
        }
        $corePaths[] = YESWIKI_SOURCE_DIR . '/templates/';
        foreach ($corePaths as $path) {
            if (file_exists($path)) {
                $this->twigLoader->addPath($path, 'core');
            }
        }

        $this->twigLoader->addPath(YESWIKI_SOURCE_DIR . '/templates/', 'shipped');

        $this->twig = new \Twig\Environment($this->twigLoader, [
            'cache' => 'cache/templates/',
            'auto_reload' => true,
        ]);

        $wikiRequest = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
        $this->twig->addGlobal('request', [
            'get' => $wikiRequest->query->all(),
            'post' => $wikiRequest->request->all(),
            'request' => array_merge($wikiRequest->query->all(), $wikiRequest->request->all()),
        ]);
        $this->twig->addGlobal('app', [
            'server' => $wikiRequest->server->all(),
            'session' => $_SESSION,
        ]);
        $this->twig->addGlobal('user', [
            'name' => (!isset($_SESSION['user']) || empty($_SESSION['user']['name'])) ? '' : $_SESSION['user']['name'],
        ]);
        $this->twig->addGlobal('config', $this->container->get(RuntimeConfig::class)->all());
        $this->twig->addGlobal('isInIframe', testUrlInIframe());

        $this->addTwigFilters();
        $this->addTwigHelper('dump', function ($var) {
            if (!empty($this->container->get(RuntimeConfig::class)['debug'])) {
                return dump($var);
            }

            return '';
        });
        $this->addTwigHelper('int', function ($content) {
            return (int)$content;
        });
        $this->addTwigHelper('_t', function ($key, $params = []) {
            return html_entity_decode(_t($key, $params));
        });

        $this->addTwigHelper('b64', function ($pValue) {
            return base64_encode($pValue);
        });

        $this->addTwigHelper('url', function ($options) {
            $options = array_merge(['tag' => '', 'handler' => '', 'params' => []], $options);
            if (substr($options['tag'], 0, 4) === 'api/') {
                $iframe = '';
            } else {
                $iframe = !empty($options['handler']) ? $options['handler'] : testUrlInIframe();
            }

            return $this->urlFormatter->href($iframe, $options['tag'], $options['params'], false);
        });
        $this->addTwigHelper('format', function ($text, $formatter = 'wakka') {
            return $this->container->get(MarkdownFormatterService::class)->format($text, $formatter);
        });
        $this->addTwigHelper('include_javascript', function ($file, $first = false, $module = false) {
            $this->assetRegistry->addJsFile($file, $first, $module);
        });

        $this->addTwigHelper('declared_assets', function () {
            return $this->assetRegistry->drain()->toHtml();
        });

        $this->addTwigHelper('page_state', function () {
            $coreAssets = $this->container->get(CoreAssets::class);
            $flash = $this->container->get(FlashMessageService::class)->getMessage();

            return '<script>' . $coreAssets->pageStateScript() . '</script>'
                . ($flash === '' || $flash === null
                    ? ''
                    : '<div class="yw-flash" hidden data-yw-flash="' . htmlspecialchars((string)$flash, ENT_QUOTES) . '"></div>');
        });
        $this->addTwigHelper('include_css', function ($file) {
            $this->assetRegistry->addCssFile($file);
        });
        $this->addTwigHelper('csrfToken', function ($tokenId) {
            if (is_string($tokenId)) {
                return $this->csrfTokenManager->getToken($tokenId)->getValue();
            } elseif (is_array($tokenId)) {
                if (!isset($tokenId['id'])) {
                    throw new \Exception('When array, `$tokenId` should contain `id` key !');
                }
                if (isset($tokenId['refresh']) && $tokenId['refresh'] === true) {
                    return $this->csrfTokenManager->refreshToken($tokenId['id'])->getValue();
                }

                return $this->csrfTokenManager->getToken($tokenId['id'])->getValue();
            }
            throw new \Exception('`$tokenId` should be a string or an array !');
        });
        $this->addTwigHelper('image_at', function ($url, $width = 0, $height = null) {
            $url = (string)$url;
            $width = (int)$width;
            $height = (int)($height ?: $width);
            if ($url === '') {
                return $url;
            }

            $base = $this->urlFormatter->getBaseUrl();
            if (str_starts_with($url, $base) || !preg_match('#^https?://#i', $url)) {
                if ($width < 1 || preg_match('#api/files/[^/?&]+/download#', $url) !== 1) {
                    return $url;
                }

                return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query([
                    'width' => $width,
                    'height' => $height,
                ]);
            }

            return $this->container->get(RemoteImageCache::class)->localUrl(
                $url,
                $width > 0 ? $width : null,
                $height > 0 ? $height : null
            );
        });
        $this->addTwigHelper('urlImage', function ($options) {
            if (!isset($options['fileName'])) {
                throw new \Exception('`urlImage` should be called with `fileName` key in params!');
            }
            if (!isset($options['width'])) {
                throw new \Exception('`urlImage` should be called with `width` key in params!');
            }
            if (!isset($options['height'])) {
                throw new \Exception('`urlImage` should be called with `height` key in params!');
            }
            $options = array_merge(['mode' => 'fit', 'refresh' => false], $options);

            $basePath = $this->urlFormatter->getBaseUrl() . '/';
            $resizer = $this->container->get(ImageResizer::class);
            $image_dest = $resizer->resizedFilename($options['fileName'], (string)$options['width'], (string)$options['height'], $options['mode']);
            $safeRefresh = !$this->container->get(HibernationService::class)->isWikiHibernated()
                && file_exists($image_dest)
                && filter_var($options['refresh'], FILTER_VALIDATE_BOOL)
                && $this->container->get(AclService::class)->isAdmin();
            if (!file_exists($image_dest) || $safeRefresh) {
                $result = $resizer->resize($options['fileName'], $image_dest, $options['width'], $options['height'], $options['mode']);
                if ($result != $image_dest) {
                    return $basePath . $options['fileName'];
                }

                return $basePath . $image_dest;
            }

            return $basePath . $image_dest;
        });
        $this->addTwigHelper('hasAcl', function ($acl, $tag = '', $adminCheck = true) {
            return $this->container->get(AclService::class)->check($acl, null, $adminCheck, $tag);
        });
        $this->addTwigHelper('renderAction', function ($name, $params = []) {
            return $this->container->get(Performer::class)->run($name, 'action', $params);
        });

        $this->addTwigHelper('action', function ($actionString) {
            return $this->container->get(ActionRunner::class)->action($actionString);
        });

        $this->twig->addFunction(new \Twig\TwigFunction('iconFromLegacy', function ($classString, $extraClass = '') {
            return $this->legacyIconToSprite(is_string($classString) ? $classString : null, $extraClass);
        }, ['is_safe' => ['html']]));

        $this->twig->addFunction(new \Twig\TwigFunction('icon', function ($name, $extraClass = '') {
            $class = trim('yw-icon ' . $extraClass);

            return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" aria-hidden="true"><use href="' . htmlspecialchars($this->spriteUrl(), ENT_QUOTES) . '#' . htmlspecialchars($name, ENT_QUOTES) . '"/></svg>';
        }, ['is_safe' => ['html']]));
        $this->addTwigHelper('addJavascript', function ($js) {
            $this->assetRegistry->addJs((string)$js);

            return '';
        });
        $this->addTwigHelper('reaction', function ($entry, $reactionId) {
            $form = $this->container->get(FormManager::class)->getOne($entry['form_id']);
            $found = false;
            foreach ($form['prepared'] as $i => $element) {
                if ($reactionId == $element->getPropertyName()) {
                    $found = $i;
                }
            }
            if ($found) {
                return $form['prepared'][$found]->renderStaticIfPermitted($entry);
            }
        });

        $this->addTwigHelper('customValueForEntry', function ($parameter, $field, $entry, $default = '') {
            return getCustomValueForEntry($parameter, $field, $entry, $default);
        });

        $this->addTwigHelper('hasAccess', function ($privilege, $tag = '') {
            return $this->container->get(AclService::class)->hasAccess($privilege, $tag ?: '');
        });
        $this->addTwigHelper('userIsAdmin', function () {
            return (bool)$this->container->get(AclService::class)->isAdmin();
        });
        $this->addTwigHelper('userIsOwner', function ($tag = '') {
            return $this->container->get(AclService::class)->isOwner($tag ?: '');
        });
        $this->addTwigHelper('absoluteUrl', function () {
            return getAbsoluteUrl();
        });

        $this->addTwigHelper('renderEntry', function ($showManagementBar, $entry, $form = '') {
            return renderEntryView($showManagementBar, $entry, $form ?: '');
        });

        $this->addTwigHelper('resizedImage', function ($image, $width, $height, $mode = 'crop', $separator = 'x') {
            return resizeImage('files/' . $image, "cache/image_{$width}{$separator}{$height}_" . $image, $width, $height, $mode);
        });

        $this->addTwigHelper('resizeImageTo', function ($source, $destination, $width, $height, $mode = 'fit') {
            return resizeImage($source, $destination, $width, $height, $mode);
        });

        $this->addTwigHelper('timestamp', function ($value) {
            return (int)strtotime((string)$value);
        });
        $this->addTwigHelper('removeAccents', function ($text) {
            return removeAccents((string)$text);
        });
        $this->addTwigHelper('fileExists', function ($path) {
            return file_exists((string)$path);
        });

        $this->addTwigHelper('qrCode', function ($content, $prefix = 'qrcode') {
            $cacheImage = 'cache' . DIRECTORY_SEPARATOR . $prefix . '-' . $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag() . '-' . md5($content) . '.svg';
            if (!file_exists($cacheImage) || (!empty($_GET['refresh']) && $_GET['refresh'] == '1')) {
                $this->container->get(\YesWiki\Content\Service\QrCodeService::class)->generateToFile($content, $cacheImage);
            }

            return $cacheImage;
        });
        $this->addTwigHelper('listValues', function ($listId, $parent = null) {
            return $this->container->get(ListManager::class)->getOne($listId, $parent);
        });
        $this->addTwigHelper('fileUrl', function ($fileName) {
            return $this->container->get(Storage::class)->url(BAZ_CHEMIN_UPLOAD . $fileName);
        });

        $this->addTwigHelper('layout_chrome', fn () => $this->renderLayoutChrome());

        $this->addTwigHelper('layout_root_style', fn () => $this->layoutRootStyle());

        $this->addTwigHelper('layout_edit', fn (string $part) => $this->renderChromeEditLink($part));

        $this->addTwigHelper(
            'layout_page',
            fn (string $role) => $this->container->get(LayoutService::class)->pageFor($role)
        );
    }

    /** The whole top bar: the menu toggle, the brand, the navbar and the quick menu. */
    public function renderLayoutChrome(?LayoutChrome $chrome = null): string
    {
        $chrome ??= $this->container->get(LayoutService::class)->current();

        return $this->render('@core/layout/chrome.twig', [
            'brand' => $this->renderLayout('brand', $chrome),
            'navbar' => $this->renderLayout('navbar', $chrome),
            'quickMenu' => $this->renderLayout('quick-menu', $chrome),
            'tools' => $this->renderChromeTools(),
        ]);
    }

    /** The viewer's own controls at the end of the bar: Colour scheme, and language. */
    private function renderChromeTools(): string
    {
        $current = (string)($GLOBALS['prefered_language'] ?? '');
        $available = (array)($GLOBALS['available_languages'] ?? []);
        $names = (array)($GLOBALS['languages_list'] ?? []);

        $languages = [];
        foreach ($available as $code) {
            $code = (string)$code;
            $languages[] = [
                'code' => $code,

                'label' => (string)($names[$code]['nativeName'] ?? $code),

                'href' => $this->urlFormatter->href('', $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag(), ['lang' => $code], false),
                'current' => $code === $current,
            ];
        }

        return $this->render('@core/layout/tools.twig', [
            'languages' => $languages,
            'language' => $current,
        ]);
    }

    /** The `style` attribute the document's root element wears. */
    public function layoutRootStyle(?LayoutChrome $chrome = null): string
    {
        $chrome ??= $this->container->get(LayoutService::class)->current();

        return '--yw-navbar-height: ' . $chrome->navbarHeight . 'px';
    }

    /** One of the three chrome parts, rendered from a LayoutChrome. */
    private function renderLayout(string $part, LayoutChrome $chrome): string
    {
        $current = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();

        if ($part === 'brand') {
            $logo = $chrome->logo;

            return $this->render('@core/layout/brand.twig', [
                'mode' => $chrome->brandMode,
                'title' => $chrome->title,

                'logo' => ($logo === '' || preg_match('~^([a-z][a-z0-9+.-]*:|//|/)~i', $logo) === 1)
                    ? $logo
                    : $this->container->get(Storage::class)->url($logo),

                'home' => $this->urlFormatter->href('', (string)$this->container->get(RuntimeConfig::class)['root_page'], null, false),
            ]);
        }

        if ($part === 'navbar') {
            $entries = [];
            foreach ($chrome->navbar as $entry) {
                $children = [];
                foreach ($entry['children'] as $child) {
                    $children[] = $child + [
                        'href' => $this->layoutHref($child['link']),
                        'active' => $child['link'] === $current,
                    ];
                }
                $entries[] = [
                    'label' => $entry['label'],
                    'href' => $entry['link'] === '' ? '' : $this->layoutHref($entry['link']),
                    'active' => $entry['link'] === $current,
                    'children' => $children,
                ];
            }

            return $this->render('@core/layout/navbar.twig', ['entries' => $entries]);
        }

        $entries = [];
        foreach ($chrome->quickMenu as $entry) {
            $entries[] = [
                'label' => $entry['label'],
                'href' => $this->layoutHref($entry['link']),

                'glyph' => $this->legacyIconToSprite($entry['icon']),
            ];
        }

        return $this->render('@core/layout/quick-menu.twig', [
            'entries' => $entries,

            'editChrome' => $this->renderChromeEditLink('navbar'),
            'account' => $chrome->accountButton,
        ]);
    }

    /** The pencil that opens the screen or page behind a piece of chrome. */
    private function renderChromeEditLink(string $part): string
    {
        if (!$this->container->get(AclService::class)->isAdmin()) {
            return '';
        }

        if ($part === 'navbar') {
            return $this->render('@core/layout/edit-chrome.twig', [
                'href' => $this->urlFormatter->href('', 'admin/layout', null, false),
                'label' => _t('LAYOUT_EDIT_NAVBAR'),
            ]);
        }

        $roles = ['header' => 'PageHeader', 'menu' => 'PageMenu', 'footer' => 'PageFooter'];
        if (!isset($roles[$part])) {
            return '';
        }

        $tag = $this->container->get(LayoutService::class)->pageFor($roles[$part]);

        return $this->render('@core/layout/edit-chrome.twig', [
            'href' => $this->urlFormatter->href('edit', $tag, ['incomingurl' => getAbsoluteUrl()], false),
            'label' => _t('LAYOUT_EDIT_' . strtoupper($part)),
        ]);
    }

    /** What someone typed into a layout entry, as an address. */
    private function layoutHref(string $link): string
    {
        if ($link === '' || preg_match('~^([a-z][a-z0-9+.-]*:|//|/|#)~i', $link) === 1) {
            return $link;
        }

        return $this->urlFormatter->href('', $link, null, false);
    }

    private function addTwigFilters(): void
    {
        $this->twig->addFilter(new \Twig\TwigFilter('preg_replace', function ($subject, $pattern, $replacement) {
            return preg_replace($pattern, $replacement, (string)$subject);
        }));

        $this->twig->addFilter(new \Twig\TwigFilter('moment', function ($stamp, string $format = 'LL'): string {
            if (!is_string($stamp) || trim($stamp) === '') {
                return '';
            }

            try {
                $moment = Carbon::parse($stamp);
            } catch (\Throwable) {
                return '';
            }

            $moment->locale((string)($GLOBALS['prefered_language'] ?? 'en'));

            return $moment->isoFormat($format);
        }));
    }

    /**
     * Render an icon name through the Tabler sprite: accepts a sprite symbol name directly ("gauge") or a historic FontAwesome class string ("fas fa-heart", mapped via src/icon-map.json).
     */
    public function legacyIconToSprite(?string $classString, string $extraClass = ''): ?string
    {
        static $map = null, $spriteNames = null;
        if ($map === null) {
            $map = json_decode((string)file_get_contents(YESWIKI_SOURCE_DIR . '/src/icon-map.json'), true) ?: [];
            unset($map['__comment']);

            $spriteNames = array_fill_keys($map, true) + ['star-filled' => true, 'cursor-text' => true];
        }
        foreach (explode(' ', (string)$classString) as $part) {
            $key = str_starts_with($part, 'fa-') ? substr($part, 3) : $part;
            $symbol = $map[$key] ?? (isset($spriteNames[$key]) ? $key : null);
            if ($symbol !== null) {
                $class = trim('yw-icon ' . $extraClass);

                return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" aria-hidden="true"><use href="' . htmlspecialchars($this->spriteUrl(), ENT_QUOTES) . '#' . htmlspecialchars($symbol, ENT_QUOTES) . '"/></svg>';
            }
        }

        return null;
    }

    /** Base-absolute URL of the Tabler sprite. */
    private function spriteUrl(): string
    {
        $baseUrl = (string)$this->container->get(RuntimeConfig::class)->getValue('base_url');

        return rtrim($baseUrl, '?') . 'src/assets/icons.svg';
    }

    private function addTwigHelper($name, $callback)
    {
        $function = new \Twig\TwigFunction($name, $callback);
        $this->twig->addFunction($function);
    }

    public function addGlobal($name, $options)
    {
        $this->twig->addGlobal($name, $options);
    }

    public function renderFromString(string $templateString, array $data = []): string
    {
        return $this->twig->createTemplate($templateString)->render($data);
    }

    /**
     * A squelette compiled as a Twig template, so its `head` and `body` blocks can be rendered independently -- and therefore out of order (ticket 15, ADR-0014).
     */
    public function createSquelette(string $source): \Twig\TemplateWrapper
    {
        return $this->twig->createTemplate($source);
    }

    public function renderFromStringNoEscape(string $templateString, array $data = []): string
    {
        $wrapped = '{% autoescape false %}' . $templateString . '{% endautoescape %}';

        return $this->twig->createTemplate($wrapped)->render($data);
    }

    /** Render an untrusted Twig string in a locked-down sandbox environment. */
    public function renderSandboxedFromStringNoEscape(string $templateString, array $data = []): string
    {
        $loader = new \Twig\Loader\ArrayLoader(['__sem__' => $templateString]);
        $twig = new \Twig\Environment($loader, ['autoescape' => false]);

        $policy = new \Twig\Sandbox\SecurityPolicy(
            ['if', 'for', 'set'],
            [
                'abs', 'batch', 'capitalize', 'date', 'default',
                'e', 'escape', 'filter', 'first', 'format',
                'join', 'json_encode', 'keys', 'last', 'length', 'lower',
                'map', 'merge', 'nl2br', 'number_format', 'raw',
                'reduce', 'replace', 'reverse', 'round', 'slice',
                'sort', 'split', 'striptags', 'title', 'trim', 'upper',
            ],
            [],
            [],
            ['date', 'fileUrl', 'max', 'min', 'random', 'range']
        );
        $twig->addExtension(new \Twig\Extension\SandboxExtension($policy, true));

        $baseUrl = $this->urlFormatter->getBaseUrl();
        $uploadPath = BAZ_CHEMIN_UPLOAD;
        $twig->addFunction(new \Twig\TwigFunction('fileUrl', function (string $fileName) use ($baseUrl, $uploadPath): string {
            return $baseUrl . '/' . $uploadPath . $fileName;
        }));

        return $twig->render('__sem__', $data);
    }

    /**
     * Render a template as a complete page: the squelette around <div class="page">content.
     *
     * @param array<string,mixed> $data
     */
    public function renderFullPage(string $templatePath, array $data = []): string
    {
        return $this->renderPage('<div class="page">' . $this->render($templatePath, $data) . '</div>');
    }

    /** $content wrapped in the wiki's page skeleton. */
    public function renderPage(string $content): string
    {
        return $this->container->get(ThemeManager::class)->renderPage($content);
    }

    /** The document head alone, for a surface supplying its own body. */
    public function renderHead(): string
    {
        return $this->container->get(ThemeManager::class)->renderHead();
    }

    /** Opening tag of a wiki-target form (historic Wiki::FormOpen()). */
    public function formOpen(mixed $method = '', mixed $tag = '', string $formMethod = 'post', string $class = ''): string
    {
        return $this->render('@core/_form-open.twig', compact(['method', 'tag', 'formMethod', 'class']));
    }

    /** Historic Wiki::FormClose(). */
    public function formClose(): string
    {
        return "</form>\n";
    }

    /**
     * render() with errors swallowed into an inline alert (historic Wiki::render()) -- for legacy fragments where a template failure must not take the page down.
     */
    public function renderSafely(mixed $templatePath, mixed $data): string
    {
        try {
            return $this->render($templatePath, $data);
        } catch (\Exception $e) {
            return '<div class="alert alert-danger">Error rendering ' . $templatePath . ': ' . $e->getMessage() . '</div>' . "\n";
        }
    }

    /**
     * Template names are stored data ({{entrylist template="X.tpl.html"}} in page bodies, per-page metadata): historical .tpl.html names resolve to their Twig successors since the tpl.html engine died (ticket 07).
     */
    public static function resolveLegacyTemplateName(string $templatePath): string
    {
        return preg_replace('/\.tpl\.html$/i', '.twig', $templatePath) ?? $templatePath;
    }

    public function hasTemplate($templatePath): bool
    {
        return $this->twigLoader->exists(self::resolveLegacyTemplateName($templatePath));
    }

    /**
     * Whether a template *compiles*, without rendering it or writing it anywhere.
     *
     * @throws \Twig\Error\Error which carries the line number
     */
    public function parseTemplateSource(string $name, string $source): void
    {
        $this->twig->parse($this->twig->tokenize(new \Twig\Source($source, $name)));
    }

    /**
     * Render a Twig template.
     *
     * @throws TemplateNotFound when no template matches (template names can be
     *                          stored user data, so this must stay catchable)
     */
    public function render($templatePath, $data = [])
    {
        $templatePath = self::resolveLegacyTemplateName($templatePath);
        if (!$this->twigLoader->exists($templatePath)) {
            throw new TemplateNotFound(_t('TEMPLATE_FILE_NOT_FOUND') . " : $templatePath");
        }
        $data = array_merge($data, [
            'config' => $this->container->get(RuntimeConfig::class)->all(),
        ]);

        return $this->twig->render($templatePath, $data);
    }
}
