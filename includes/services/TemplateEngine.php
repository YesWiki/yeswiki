<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;

class TemplateEngine
{
    protected $wiki;
    protected $params;
    protected $twig;

    public function __construct(Wiki $wiki, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->params = $params;
        // Default path (main namespace) is the root of the project. There are no templates
        // there, but it's needed to call relative path like render('tools/bazar/templates/...')
        $loader = new \Twig\Loader\FilesystemLoader('./');
        // Custom Extension, so we can create action and handlers inside custom folder
        foreach(["custom/templates/"] as $path) {
            if (file_exists($path)) $loader->addPath($path, 'custom');
        }        
        // Extensions templates paths (added by priority order)
        foreach ($this->wiki->extensions as $extensionName => $pluginInfo) {
            // Ability to override an extension template from the custom folder
            $paths = ["custom/templates/$extensionName/"];
            // Ability to override an extension template from another extension
            foreach ($this->wiki->extensions as $otherExtensionName => $pluginInfo) {
                $paths[] = "tools/$otherExtensionName/templates/$extensionName/";
            }   
            // Standard path for an extension template
            $paths[] = "tools/$extensionName/templates/";
            foreach($paths as $path) {
                if (file_exists($path)) {
                    // second argument provide namespace, so we when we render '@bazar/bazaraliste.twig'
                    // it will look first in custom/templates/bazar/, 
                    // then in tools/XXX/templates/bazar/ 
                    // and finally in tools/bazar/templates/
                    $loader->addPath($path, $extensionName);
                }
            }
        }
        // Set up twig
        $this->twig = new \Twig\Environment($loader, [
            'cache' => 'cache/templates/',
            'auto_reload' => true
        ]);
    }

    public function renderInSquelette($templatePath, $data = []) 
    {
        $result = '';
        $result .= $this->wiki->Header();
        $result .= $this->render($templatePath, $data);
        $result .= $this->wiki->Footer();
        return $result;
    }
    public function render($templatePath, $data = [])
    {
        $data = array_merge($data, [
            'config' => $this->wiki->config,
            'request' => $_GET,
        ]);
        return $this->twig->render($templatePath, $data);
    }
}
