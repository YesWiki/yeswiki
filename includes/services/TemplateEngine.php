<?php

namespace YesWiki\Core\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Wiki;

class TemplateNotFound extends \Exception
{
}

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
                         "themes/{$config->get('favorite_theme')}/tools"
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
            'auto_reload' => true
        ]);

        // Adds Helpers
        $this->addTwigHelper('_t', function ($key, $params = []) {
            return html_entity_decode(_t($key, $params));
        });
        $this->addTwigHelper('url', function ($options) {
            $options = array_merge(['tag' => '', 'handler' => '', 'params' => []], $options);
            $iframe = !empty($options['handler']) ? $options['handler'] : testUrlInIframe();
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
        $this->addTwigHelper('crsfToken', function ($tokenId) {
            if (is_string($tokenId)) {
                return $this->csrfTokenManager->getToken($tokenId);
            } elseif (is_array($tokenId)) {
                if (!isset($tokenId['id'])) {
                    throw new Exception("When array, `\$tokenId` should contain `id` key !");
                } else {
                    if (isset($tokenId['refresh']) && $tokenId['refresh'] === true) {
                        return $this->csrfTokenManager->grefreshToken($tokenId['id']);
                    } else {
                        return $this->csrfTokenManager->getToken($tokenId['id']);
                    }
                }
            } else {
                throw new Exception("`\$tokenId` should be a string or an array !");
            }
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
        $result = $this->wiki->Header().$result;
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
            'request' => $_GET,
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
