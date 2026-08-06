<?php

namespace YesWiki\Render\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ImageResizer;
use YesWiki\Content\Service\ListManager;
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
        // Default paths (main namespace): the instance dir then the source tree. There are no
        // templates at either root, but it's needed to call relative path like
        // render('extensions/myext/templates/...') - resolved instance-first so custom overrides win
        $this->twigLoader = new \Twig\Loader\FilesystemLoader(['./', YESWIKI_SOURCE_DIR]);

        // Custom Extension, so we can create action and handlers inside custom folder
        if (file_exists('custom/templates/')) {
            $this->twigLoader->addPath('custom/templates/', 'custom');
        }
        // Extensions templates paths (added by priority order)
        foreach ($this->container->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all() as $extensionName => $pluginInfo) {
            // Ability to override an extension template from the custom folder
            $paths = ["custom/templates/$extensionName/"];
            // Ability to override an extension template from the legacy directories, should not be used anymore for new templates.
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

            // Ability to override an extension template from another extension
            foreach ($this->container->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all() as $otherExtensionName => $otherExtensionPath) {
                $paths[] = "custom/extensions/$otherExtensionName/templates/$extensionName/";
                $paths[] = $otherExtensionPath . "templates/$extensionName/";
            }
            // Standard path for an extension template
            $paths[] = YESWIKI_SOURCE_DIR . "/extensions/$extensionName/templates/";
            // Legacy directories, should not be used anymore for new templates. Maybe
            // of them are not used by anybody, but just in case we keep them for backward compatibility
            $paths[] = YESWIKI_SOURCE_DIR . "/extensions/$extensionName/presentation/templates/";

            foreach ($paths as $path) {
                if (file_exists($path)) {
                    $this->twigLoader->addPath($path, $extensionName);
                }
            }
        }

        // Core templates
        $corePaths = [];
        $corePaths[] = 'custom/templates/core/';
        // Ability to override an extension template from another extensioncore
        foreach ($this->container->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all() as $otherExtensionName => $otherExtensionPath) {
            $corePaths[] = $otherExtensionPath . 'templates/core/';
        }
        $corePaths[] = YESWIKI_SOURCE_DIR . '/templates/';
        foreach ($corePaths as $path) {
            if (file_exists($path)) {
                $this->twigLoader->addPath($path, 'core');
            }
        }

        // The templates as shipped -- `custom/templates/` is deliberately NOT on this
        // namespace (ticket 30). One screen needs it: the one that edits the overrides. A
        // broken override takes out every page, and if it also takes out the screen that
        // fixes it, the only way back is FTP. `@shipped/admin/custom-templates.twig` and the
        // shell it extends are therefore the two templates in the wiki that cannot be
        // overridden -- which is the whole safety net, so do not "tidy" this into @core.
        $this->twigLoader->addPath(YESWIKI_SOURCE_DIR . '/templates/', 'shipped');

        // Set up twig
        $this->twig = new \Twig\Environment($this->twigLoader, [
            'cache' => 'cache/templates/',
            'auto_reload' => true,
        ]);

        // Adds Globals
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

        // Adds Helpers
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
        // ticket 15: emits every declared asset, once, at the end of the squelette's head
        // block. Anything an action registers *after* this point in that block is too late.
        $this->addTwigHelper('declared_assets', function () {
            return $this->assetRegistry->drain()->toHtml();
        });
        // ticket 16: the per-page state a boosted navigation has to refresh -- the `wiki`
        // globals and any flash message. Rendered *inside* the body block, which is what a
        // boosted navigation swaps, so both are current on every page rather than only on the
        // first one. Goes first in that block: inline markup further down calls _t().
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
                    // do nothing : error
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
        // squelettes: same attribute-string form the historical `{{action attr="…"}}`
        // squelette syntax used, with Wiki::Action()'s link-tracking semantics
        $this->addTwigHelper('action', function ($actionString) {
            return $this->container->get(ActionRunner::class)->action($actionString);
        });
        // inline JS registered for the page footer aggregate, like AddJavascript()
        // calls from the historical PHP templates
        // stored data (reaction images, bazar marker icons...) may still carry historic
        // FontAwesome class strings: render them through the sprite when a mapping exists
        $this->twig->addFunction(new \Twig\TwigFunction('iconFromLegacy', function ($classString, $extraClass = '') {
            return $this->legacyIconToSprite(is_string($classString) ? $classString : null, $extraClass);
        }, ['is_safe' => ['html']]));

        // Tabler sprite icon (src/assets/icons.svg): icon('trash'), icon('star', 'yw-icon--lg').
        // Registered is_safe: the produced markup is fully escaped here, so templates can
        // write {{ icon('x') }} without |raw
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
        // bazar list templates: resolve a display parameter (color=, icon=, ...)
        // for one entry — delegates to getCustomValueForEntry() (bazar.functions.php)
        $this->addTwigHelper('customValueForEntry', function ($parameter, $field, $entry, $default = '') {
            return getCustomValueForEntry($parameter, $field, $entry, $default);
        });
        // ticket 07 (tpl.html -> Twig): the page-list/layout templates check page
        // rights inline; these mirror the Wiki calls the PHP templates used
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
        // full rendered view of one bazar entry (liste_accordeon expands entries
        // in place) — delegates to renderEntryView()
        $this->addTwigHelper('renderEntry', function ($showManagementBar, $entry, $form = '') {
            return renderEntryView($showManagementBar, $entry, $form ?: '');
        });
        // thumbnail with the historical cache/image_{W}x{H}_{name} naming the bazar
        // list templates share (agenda uses WxH, blog/trombinoscope W_H -- the
        // separator is part of each template's stored-cache contract)
        $this->addTwigHelper('resizedImage', function ($image, $width, $height, $mode = 'crop', $separator = 'x') {
            return resizeImage('files/' . $image, "cache/image_{$width}{$separator}{$height}_" . $image, $width, $height, $mode);
        });
        // raw source->destination passthrough for the odd call shapes (placeholder
        // sources, custom cache names) -- unlike resizedImage, which derives the
        // cache name from the files/ image name
        $this->addTwigHelper('resizeImageTo', function ($source, $destination, $width, $height, $mode = 'fit') {
            return resizeImage($source, $destination, $width, $height, $mode);
        });
        // strtotime() with the legacy templates' semantics: unparseable or missing
        // dates become 0 (epoch), never an exception -- entry dates are user data
        $this->addTwigHelper('timestamp', function ($value) {
            return (int)strtotime((string)$value);
        });
        $this->addTwigHelper('removeAccents', function ($text) {
            return removeAccents((string)$text);
        });
        $this->addTwigHelper('fileExists', function ($path) {
            return file_exists((string)$path);
        });
        // qrcode badge templates: returns the cached SVG path for a payload,
        // generating it on first use (?refresh=1 regenerates)
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
            return $this->urlFormatter->getBaseUrl() . '/' . BAZ_CHEMIN_UPLOAD . $fileName;
        });

        // ticket 30: the wiki's chrome, from configuration rather than from the three pages
        // `PageTitre` / `PageMenuHaut` / `PageRapideHaut`. A squelette calls these three; the
        // three chrome pages that are still pages keep going through {{include}}.
        $this->addTwigHelper('layout_chrome', fn () => $this->renderLayoutChrome());
        // ...and the root style attribute the whole document wears, which is where the
        // navbar height lives. Separate from the chrome because it goes on <html>.
        $this->addTwigHelper('layout_root_style', fn () => $this->layoutRootStyle());
        // ...and the pencil that opens whichever bit of the chrome it sits on, for whoever
        // may follow it. `''` for everyone else, so a squelette needs no permission test.
        $this->addTwigHelper('layout_edit', fn (string $part) => $this->renderChromeEditLink($part));
        // ...and which page a squelette should include for a chrome role, which is the
        // canonical name unless the page being rendered names another one for itself
        $this->addTwigHelper(
            'layout_page',
            fn (string $role) => $this->container->get(LayoutService::class)->pageFor($role)
        );
    }

    /**
     * The whole top bar: the menu toggle, the brand, the navbar and the quick menu.
     *
     * One call rather than three, because it is one swap: the live preview on `/admin/layout`
     * replaces this block wholesale with the same fragment rendered from the posted form
     * (AdminController::layoutPreview). A squelette gets the configured chrome by passing
     * nothing.
     */
    public function renderLayoutChrome(?LayoutChrome $chrome = null): string
    {
        $chrome ??= $this->container->get(LayoutService::class)->current();

        return $this->render('@core/layout/chrome.twig', [
            'brand' => $this->renderLayout('brand', $chrome),
            'navbar' => $this->renderLayout('navbar', $chrome),
            'quickMenu' => $this->renderLayout('quick-menu', $chrome),
        ]);
    }

    /**
     * The `style` attribute the document's root element wears.
     *
     * Where the navbar height goes, and an inline style is the point rather than a shortcut:
     * a custom property declared here beats the same property declared in *any* stylesheet,
     * preset or hand-written, without needing `!important` anywhere. Which is what the
     * setting means -- a number typed on the Layout screen has the last word over what a
     * preset happens to say (ticket 30).
     */
    public function layoutRootStyle(?LayoutChrome $chrome = null): string
    {
        $chrome ??= $this->container->get(LayoutService::class)->current();

        return '--yw-navbar-height: ' . $chrome->navbarHeight . 'px';
    }

    /**
     * One of the three chrome parts, rendered from a LayoutChrome.
     *
     * From a value object rather than from LayoutService, because the live preview on
     * `/admin/layout` renders a *draft* -- the chrome a posted form describes, which has not
     * been saved. Passing it in is what lets the preview go through this exact code path
     * instead of a second one that would drift.
     *
     * The links are resolved here rather than in LayoutService: what an entry stores is what
     * someone typed -- a page name, a route, or a full URL -- and turning that into an href
     * is UrlFormatter's job, which the service has no business holding for a value that is
     * only ever needed at render time.
     */
    private function renderLayout(string $part, LayoutChrome $chrome): string
    {
        $current = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();

        if ($part === 'brand') {
            $logo = $chrome->logo;

            return $this->render('@core/layout/brand.twig', [
                'mode' => $chrome->brandMode,
                'title' => $chrome->title,
                // an address is used as it stands -- the file picker stores the file's own
                // `api/files/…/download` URL, and a wiki may point at an image elsewhere.
                // Anything else is an instance-relative path (files/logo.png) and is resolved
                // against the base URL, so it survives path-shaped page URLs.
                'logo' => ($logo === '' || preg_match('~^([a-z][a-z0-9+.-]*:|//|/)~i', $logo) === 1)
                    ? $logo
                    : $this->urlFormatter->getBaseUrl() . '/' . $logo,
                // false, like every other href handed to a template here: Twig escapes it
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
                // stored icons may be sprite names or historic FontAwesome classes
                'glyph' => $this->legacyIconToSprite($entry['icon']),
            ];
        }

        return $this->render('@core/layout/quick-menu.twig', [
            'entries' => $entries,
            // last inside this block, which is what puts it at the extreme right: the block
            // itself is floated right, so a sibling *after* it would land to its LEFT
            'editChrome' => $this->renderChromeEditLink('navbar'),
            'account' => $chrome->accountButton,
        ]);
    }

    /**
     * The pencil that opens the screen or page behind a piece of chrome.
     *
     * **Admins only, all three of them.** The navbar one has no choice -- `/admin/layout` is
     * admin-gated, so anyone else would follow it into a refusal. The banner and the footer
     * could in principle use write access to the page, and that would be wrong here: a
     * default YesWiki is an open wiki, so `hasAccess('write')` is true for anonymous visitors
     * and every reader of every page would get two pencils on the site's furniture. Editing
     * those pages is still open to whoever may write them -- the includes carry
     * `doubleclick="1"` -- this is only about who is *offered* it unprompted.
     *
     * Returns '' rather than a disabled control when the answer is no: an affordance nobody
     * can use is worse than none, and it saves every squelette a permission test.
     */
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

        // the *resolved* page, because a page may name a different banner for itself
        $tag = $this->container->get(LayoutService::class)->pageFor($roles[$part]);

        // `incomingurl`, so saving comes back to the page you were reading rather than
        // stranding you on `PageHeader` -- which is a page nobody reads, only edits. The
        // wiki's own convention for this (EntryController, DeletepageHandler); EditHandler
        // learned to honour it for pages at the same time as this.
        return $this->render('@core/layout/edit-chrome.twig', [
            // `false`: href() HTML-escapes by default, and this value is then escaped AGAIN
            // by Twig on its way into the attribute -- `&` came out as `&amp;amp;` and the
            // link led nowhere. Twig does the one escape that is wanted.
            'href' => $this->urlFormatter->href('edit', $tag, ['incomingurl' => getAbsoluteUrl()], false),
            'label' => _t('LAYOUT_EDIT_' . strtoupper($part)),
        ]);
    }

    /**
     * What someone typed into a layout entry, as an address.
     *
     * Anything with a scheme, an anchor or a leading slash is already an address and is left
     * alone -- a menu entry to another site is an ordinary thing to want. Everything else is
     * a page name or a route of this wiki (`BacASable`, `search`, `dashboard/forms`).
     */
    private function layoutHref(string $link): string
    {
        if ($link === '' || preg_match('~^([a-z][a-z0-9+.-]*:|//|/|#)~i', $link) === 1) {
            return $link;
        }

        return $this->urlFormatter->href('', $link, null, false);
    }

    private function addTwigFilters(): void
    {
        // ticket 07: the converted bazar-list templates normalize markup with the
        // same regexes their PHP predecessors used
        $this->twig->addFilter(new \Twig\TwigFilter('preg_replace', function ($subject, $pattern, $replacement) {
            return preg_replace($pattern, $replacement, (string)$subject);
        }));
    }

    /**
     * Render an icon name through the Tabler sprite: accepts a sprite symbol name
     * directly ("gauge") or a historic FontAwesome class string ("fas fa-heart",
     * mapped via src/icon-map.json). Null when nothing resolves.
     */
    public function legacyIconToSprite(?string $classString, string $extraClass = ''): ?string
    {
        static $map = null, $spriteNames = null;
        if ($map === null) {
            $map = json_decode((string)file_get_contents(YESWIKI_SOURCE_DIR . '/src/icon-map.json'), true) ?: [];
            unset($map['__comment']);
            // ids actually present in the sprite: the map's values + the
            // generator's EXTRAS (see src/build-icon-sprite.mjs)
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

    /**
     * Base-absolute URL of the Tabler sprite. A bare "src/assets/icons.svg" would
     * resolve against the current page path and 404 on path-shaped URLs
     * (rewrite-mode handlers like /PageTag/edit).
     */
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
     * A squelette compiled as a Twig template, so its `head` and `body` blocks can be
     * rendered independently -- and therefore out of order (ticket 15, ADR-0014).
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

    /**
     * Render an untrusted Twig string in a locked-down sandbox environment.
     *
     * A fresh Twig instance is created with no globals, no custom functions,
     * and a strict SecurityPolicy so that administrator-supplied template strings
     * cannot call PHP functions or access server internals.
     */
    public function renderSandboxedFromStringNoEscape(string $templateString, array $data = []): string
    {
        $loader = new \Twig\Loader\ArrayLoader(['__sem__' => $templateString]);
        $twig = new \Twig\Environment($loader, ['autoescape' => false]);

        $policy = new \Twig\Sandbox\SecurityPolicy(
            // allowed control-flow tags only
            ['if', 'for', 'set'],
            // safe data-manipulation and formatting filters
            [
                'abs', 'batch', 'capitalize', 'date', 'default',
                'e', 'escape', 'filter', 'first', 'format',
                'join', 'json_encode', 'keys', 'last', 'length', 'lower',
                'map', 'merge', 'nl2br', 'number_format', 'raw',
                'reduce', 'replace', 'reverse', 'round', 'slice',
                'sort', 'split', 'striptags', 'title', 'trim', 'upper',
            ],
            // no method calls on objects
            [],
            // no property access on objects
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
     * (Previously named renderInSquelette.).
     *
     * @param array<string,mixed> $data
     */
    public function renderFullPage(string $templatePath, array $data = []): string
    {
        return $this->renderPage('<div class="page">' . $this->render($templatePath, $data) . '</div>');
    }

    /**
     * $content wrapped in the wiki's page skeleton.
     *
     * Replaces the header()/footer() pair every caller used to bracket its output with
     * (ticket 15). A pair could not survive the head being rendered last: the content has to
     * be a value before the skeleton renders, or `<head>` cannot know what it declared.
     */
    public function renderPage(string $content): string
    {
        return $this->container->get(ThemeManager::class)->renderPage($content);
    }

    /** The document head alone, for a surface supplying its own body. Call it after the content is built. */
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
     * render() with errors swallowed into an inline alert (historic Wiki::render()) --
     * for legacy fragments where a template failure must not take the page down.
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
     * Template names are stored data ({{entrylist template="X.tpl.html"}} in page
     * bodies, per-page metadata): historical .tpl.html names resolve to their Twig
     * successors since the tpl.html engine died (ticket 07).
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
     * For the Custom Templates screen (ticket 30), which must not accept an override that
     * cannot parse: the failure would not show up where it was made but as a 500 on every
     * page that renders the template.
     *
     * It has to be **this** environment rather than a throwaway one. Twig 3 resolves filters
     * and functions at parse time, so a bare environment would reject `{{ _t(…) }}`,
     * `{{ action(…) }}` and every other helper as unknown -- reporting a syntax error in
     * templates that are perfectly correct.
     *
     * @throws \Twig\Error\Error which carries the line number
     */
    public function parseTemplateSource(string $name, string $source): void
    {
        $this->twig->parse($this->twig->tokenize(new \Twig\Source($source, $name)));
    }

    /**
     * Render a Twig template. The namespace picks the search path: '@core/x.twig'
     * looks in custom/templates/core/ then templates/, '@myext/x.twig' in the
     * extension override chain then extensions/myext/templates/.
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
