<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Content\Attach;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Exception\TemplateNotFound;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Wiki;

class TemplateEngine
{
    protected $wiki;
    protected $twigLoader;
    protected $twig;
    protected $assetsManager;
    protected $csrfTokenManager;

    protected UrlFormatter $urlFormatter;

    public function __construct(
        Wiki $wiki,
        ParameterBagInterface $config,
        AssetsManager $assetsManager,
        CsrfTokenManager $csrfTokenManager,
        UrlFormatter $urlFormatter
    ) {
        $this->urlFormatter = $urlFormatter;
        $this->wiki = $wiki;
        $this->assetsManager = $assetsManager;
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
        foreach ($this->wiki->extensions as $extensionName => $pluginInfo) {
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
            foreach ($this->wiki->extensions as $otherExtensionName => $otherExtensionPath) {
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
        foreach ($this->wiki->extensions as $otherExtensionName => $otherExtensionPath) {
            $corePaths[] = $otherExtensionPath . 'templates/core/';
        }
        $corePaths[] = YESWIKI_SOURCE_DIR . '/templates/';
        foreach ($corePaths as $path) {
            if (file_exists($path)) {
                $this->twigLoader->addPath($path, 'core');
            }
        }

        // Set up twig
        $this->twig = new \Twig\Environment($this->twigLoader, [
            'cache' => 'cache/templates/',
            'auto_reload' => true,
        ]);

        // Adds Globals
        $wikiRequest = $this->wiki->request;
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
        $this->twig->addGlobal('config', $this->wiki->config);
        $this->twig->addGlobal('isInIframe', testUrlInIframe());

        // Adds Helpers
        $this->addTwigFilters();
        $this->addTwigHelper('dump', function ($var) {
            if (!empty($this->wiki->config['debug'])) {
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
            return $this->wiki->services->get(MarkdownFormatterService::class)->format($text, $formatter);
        });
        $this->addTwigHelper('include_javascript', function ($file, $first = false, $module = false) {
            $this->assetsManager->AddJavascriptFile($file, $first, $module);
        });
        $this->addTwigHelper('include_css', function ($file) {
            $this->assetsManager->AddCSSFile($file);
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
            $attach = new Attach($this->wiki);
            $image_dest = $attach->getResizedFilename($options['fileName'], $options['width'], $options['height'], $options['mode']);
            $safeRefresh = !$this->wiki->services->get(HibernationService::class)->isWikiHibernated()
                && file_exists($image_dest)
                && filter_var($options['refresh'], FILTER_VALIDATE_BOOL)
                && $this->wiki->services->get(AclService::class)->isAdmin();
            if (!file_exists($image_dest) || $safeRefresh) {
                $result = $attach->redimensionner_image($options['fileName'], $image_dest, $options['width'], $options['height'], $options['mode']);
                if ($result != $image_dest) {
                    // do nothing : error
                    return $basePath . $options['fileName'];
                }

                return $basePath . $image_dest;
            }

            return $basePath . $image_dest;
        });
        $this->addTwigHelper('hasAcl', function ($acl, $tag = '', $adminCheck = true) {
            return $this->wiki->services->get(AclService::class)->check($acl, null, $adminCheck, $tag);
        });
        $this->addTwigHelper('renderAction', function ($name, $params = []) {
            return $this->wiki->services->get(Performer::class)->run($name, 'action', $params);
        });
        // squelettes: same attribute-string form the historical `{{action attr="…"}}`
        // squelette syntax used, with Wiki::Action()'s link-tracking semantics
        $this->addTwigHelper('action', function ($actionString) {
            return $this->wiki->services->get(ActionRunner::class)->action($actionString);
        });
        // inline JS registered for the page footer aggregate, like AddJavascript()
        // calls from the historical PHP templates
        $this->addTwigHelper('addJavascript', function ($js) {
            $this->assetsManager->AddJavascript((string)$js);

            return '';
        });
        $this->addTwigHelper('reaction', function ($entry, $reactionId) {
            $form = $this->wiki->services->get(FormManager::class)->getOne($entry['form_id']);
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
            return $this->wiki->services->get(AclService::class)->hasAccess($privilege, $tag ?: '');
        });
        $this->addTwigHelper('userIsAdmin', function () {
            return (bool)$this->wiki->services->get(AclService::class)->isAdmin();
        });
        $this->addTwigHelper('userIsOwner', function ($tag = '') {
            return $this->wiki->services->get(AclService::class)->isOwner($tag ?: '');
        });
        $this->addTwigHelper('absoluteUrl', function () {
            return getAbsoluteUrl();
        });
        // full rendered view of one bazar entry (liste_accordeon expands entries
        // in place) — delegates to baz_voir_fiche()
        $this->addTwigHelper('renderEntry', function ($barregestion, $fiche, $form = '') {
            return baz_voir_fiche($barregestion, $fiche, $form ?: '');
        });
        // thumbnail with the historical cache/image_{W}x{H}_{name} naming the bazar
        // list templates share (agenda uses WxH, blog/trombinoscope W_H -- the
        // separator is part of each template's stored-cache contract)
        $this->addTwigHelper('resizedImage', function ($image, $width, $height, $mode = 'crop', $separator = 'x') {
            return redimensionner_image('files/' . $image, "cache/image_{$width}{$separator}{$height}_" . $image, $width, $height, $mode);
        });
        // raw source->destination passthrough for the odd call shapes (placeholder
        // sources, custom cache names) -- unlike resizedImage, which derives the
        // cache name from the files/ image name
        $this->addTwigHelper('resizeImageTo', function ($source, $destination, $width, $height, $mode = 'fit') {
            return redimensionner_image($source, $destination, $width, $height, $mode);
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
            $cacheImage = 'cache' . DIRECTORY_SEPARATOR . $prefix . '-' . $this->wiki->getPageTag() . '-' . md5($content) . '.svg';
            if (!file_exists($cacheImage) || (!empty($_GET['refresh']) && $_GET['refresh'] == '1')) {
                $this->wiki->services->get(\YesWiki\Content\Service\QrCodeService::class)->generateToFile($content, $cacheImage);
            }

            return $cacheImage;
        });
        $this->addTwigHelper('listValues', function ($listId, $parent = null) {
            return $this->wiki->services->get(ListManager::class)->getOne($listId, $parent);
        });
        $this->addTwigHelper('fileUrl', function ($fileName) {
            return $this->urlFormatter->getBaseUrl() . '/' . BAZ_CHEMIN_UPLOAD . $fileName;
        });
    }

    private function addTwigFilters(): void
    {
        // ticket 07: the converted bazar-list templates normalize markup with the
        // same regexes their PHP predecessors used
        $this->twig->addFilter(new \Twig\TwigFilter('preg_replace', function ($subject, $pattern, $replacement) {
            return preg_replace($pattern, $replacement, (string)$subject);
        }));
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
     * Render a template as a complete page: squelette header + <div class="page">
     * content + squelette footer. (Previously named renderInSquelette.).
     *
     * @param array<string,mixed> $data
     */
    public function renderFullPage(string $templatePath, array $data = []): string
    {
        $result = '<div class="page">';
        $result .= $this->render($templatePath, $data);
        $result .= '</div>';
        $result = $this->wiki->Header() . $result;
        $result .= $this->wiki->Footer();

        return $result;
    }

    /**
     * Template names are stored data ({{bazarliste template="X.tpl.html"}} in page
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
            'config' => $this->wiki->config,
        ]);

        return $this->twig->render($templatePath, $data);
    }
}
