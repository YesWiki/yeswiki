<?php

namespace YesWiki\Core\Service;

use attach;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Core\Exception\TemplateNotFound;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class TemplateEngine
{
    protected $wiki;
    protected $twigLoader;
    protected $twig;
    protected $assetsManager;
    protected $csrfTokenManager;

    public function __construct(
        Wiki $wiki,
        ParameterBagInterface $config,
        AssetsManager $assetsManager,
        CsrfTokenManager $csrfTokenManager
    ) {
        $this->wiki = $wiki;
        $this->assetsManager = $assetsManager;
        $this->csrfTokenManager = $csrfTokenManager;
        // Default path (main namespace) is the root of the project. There are no templates
        // there, but it's needed to call relative path like render('tools/bazar/templates/...')
        $this->twigLoader = new \Twig\Loader\FilesystemLoader('./');

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
            foreach ([
                'custom/templates',
                'templates',
                'themes/tools',
                "themes/{$config->get('favorite_theme')}/tools",
            ] as $dir) {
                $paths[] = $dir . '/' . $extensionName . '/templates/';
                $paths[] = $dir . '/' . $extensionName . '/';
            }
            // Ability to override an extension template from another extension
            foreach ($this->wiki->extensions as $otherExtensionName => $pluginInfo) {
                $paths[] = "tools/$otherExtensionName/templates/$extensionName/";
            }
            // Standard path for an extension template
            $paths[] = "tools/$extensionName/templates/";
            // Legacy directories, should not be used anymore for new templates. Maybe
            // of them are not used by anybody, but just in case we keep them for backward compatibility
            $paths[] = "tools/$extensionName/presentation/templates/";

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
        foreach ($this->wiki->extensions as $otherExtensionName => $pluginInfo) {
            $corePaths[] = "tools/$otherExtensionName/templates/core/";
        }
        $corePaths[] = 'templates/';
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
        $this->twig->addGlobal('request', [
            'get' => $_GET,
            'post' => $_POST,
            'request' => $_REQUEST,
        ]);
        $this->twig->addGlobal('app', [
            'server' => $_SERVER,
            'session' => $_SESSION,
        ]);
        $this->twig->addGlobal('config', $this->wiki->config);
        $this->twig->addGlobal('isInIframe', testUrlInIframe());

        // Adds Helpers
        $this->addTwigHelper('_t', function ($key, $params = []) {
            return html_entity_decode(_t($key, $params));
        });
        $this->addTwigHelper('url', function ($options) {
            $options = array_merge(['tag' => '', 'handler' => '', 'params' => []], $options);
            if (substr($options['tag'], 0, 4) === 'api/') {
                $iframe = '';
            } else {
                $iframe = !empty($options['handler']) ? $options['handler'] : testUrlInIframe();
            }

            return $this->wiki->Href($iframe, $options['tag'], $options['params'], false);
        });
        $this->addTwigHelper('format', function ($text, $formatter = 'wakka') {
            return $this->wiki->Format($text, $formatter);
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
                    throw new Exception('When array, `$tokenId` should contain `id` key !');
                } else {
                    if (isset($tokenId['refresh']) && $tokenId['refresh'] === true) {
                        return $this->csrfTokenManager->refreshToken($tokenId['id'])->getValue();
                    } else {
                        return $this->csrfTokenManager->getToken($tokenId['id'])->getValue();
                    }
                }
            } else {
                throw new Exception('`$tokenId` should be a string or an array !');
            }
        });
        $this->addTwigHelper('urlImage', function ($options) {
            if (!isset($options['fileName'])) {
                throw new Exception('`urlImage` should be called with `fileName` key in params!');
            }
            if (!isset($options['width'])) {
                throw new Exception('`urlImage` should be called with `width` key in params!');
            }
            if (!isset($options['height'])) {
                throw new Exception('`urlImage` should be called with `height` key in params!');
            }
            $options = array_merge(['mode' => 'fit', 'refresh' => false], $options);

            if (!class_exists('attach')) {
                include 'tools/attach/libs/attach.lib.php';
            }
            $basePath = $this->wiki->getBaseUrl() . '/';
            $attach = new attach($this->wiki);
            $image_dest = $attach->getResizedFilename($options['fileName'], $options['width'], $options['height'], $options['mode']);
            $safeRefresh = !$this->wiki->services->get(SecurityController::class)->isWikiHibernated()
                && file_exists($image_dest)
                && filter_var($options['refresh'], FILTER_VALIDATE_BOOL)
                && $this->wiki->UserIsAdmin();
            if (!file_exists($image_dest) || $safeRefresh) {
                $result = $attach->redimensionner_image($options['fileName'], $image_dest, $options['width'], $options['height'], $options['mode']);
                if ($result != $image_dest) {
                    // do nothing : error
                    return $basePath . $options['fileName'];
                }

                return $basePath . $image_dest;
            } else {
                return $basePath . $image_dest;
            }
        });
        $this->addTwigHelper('hasAcl', function ($acl, $tag = '', $adminCheck = true) {
            return $this->wiki->services->get(AclService::class)->check($acl, null, $adminCheck, $tag);
        });
        $this->addTwigHelper('renderAction', function ($name, $params = []) {
            return $this->wiki->services->get(Performer::class)->run($name, 'action', $params);
        });
    }

    private function addTwigHelper($name, $callback)
    {
        $function = new \Twig\TwigFunction($name, $callback);
        $this->twig->addFunction($function);
    }

    public function renderInSquelette($templatePath, $data = [])
    {
        $result = '<div class="page">';
        $result .= $this->render($templatePath, $data);
        $result .= '</div>';
        $result = $this->wiki->Header() . $result;
        $result .= $this->wiki->Footer();

        return $result;
    }

    public function hasTemplate($templatePath): bool
    {
        return $this->twigLoader->exists($templatePath);
    }

    // second argument provide namespace, so we when we render '@bazar/bazarliste.twig'
    // it will look first in custom/templates/bazar/,
    // then in tools/XXX/templates/bazar/
    // and finally in tools/bazar/templates/
    public function render($templatePath, $data = [])
    {
        $method = endsWith($templatePath, '.twig') ? 'renderTwig' : 'renderPhp';

        return $this->$method($templatePath, $data);
    }

    protected function renderTwig($templatePath, $data = [])
    {
        $data = array_merge($data, [
            'config' => $this->wiki->config,
        ]);

        return $this->twig->render($templatePath, $data);
    }

    /**
     * @throws TemplateNotFound
     */
    protected function renderPhp($templatePath, $data = [])
    {
        if (!$this->hasTemplate($templatePath)) {
            throw new TemplateNotFound(_t('TEMPLATE_FILE_NOT_FOUND') . " : $templatePath");
        }

        $realTemplatePath = $this->twigLoader->getSourceContext($templatePath)->getPath();

        // extract variables for the template
        if (!empty($data)) {
            extract($data);
        }

        ob_start(); // buffer
        include $realTemplatePath;
        $content = ob_get_contents(); // get buffer's content
        ob_end_clean(); // destroy buffer

        return $content;
    }
}
