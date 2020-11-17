<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;

class TemplateNotFound extends \Exception {}

class TemplateEngine
{
    protected $wiki;
    protected $twigLoader;
    protected $twig;
    protected $paths = []; // paths where the templates can be put

    public function __construct(Wiki $wiki, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        // Default path (main namespace) is the root of the project. There are no templates
        // there, but it's needed to call relative path like render('tools/bazar/templates/...')
        $this->twigLoader = new \Twig\Loader\FilesystemLoader('./');
        
        // Custom Extension, so we can create action and handlers inside custom folder
        if (file_exists("custom/templates/")) $this->addPath("custom/templates/", 'custom');        
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
            // Legacy directories, should not be used anymore for new templates. Maybe
            // of them are not used by anybody, but just in case we keep them for backward compatibility
            $paths[] = "tools/$extensionName/presentation/templates/";
            $paths[] = "custom/themes/tools/$extensionName/templates/";
            foreach(['custom/templates', 'templates', 'themes/tools', 
                     "themes/{$params->get('favorite_theme')}/tools"] as $dir) {
                $paths[] = $dir.'/'.$extensionName.'/templates/';
                $paths[] = $dir.'/'.$extensionName.'/';
            }

            foreach($paths as $path) {
                if (file_exists($path)) $this->addPath($path, $extensionName);
            }
        }

        // Set up twig
        $this->twig = new \Twig\Environment($this->twigLoader, [
            'cache' => 'cache/templates/',
            'auto_reload' => true
        ]);
    }

    // second argument provide namespace, so we when we render '@bazar/bazaraliste.twig'
    // it will look first in custom/templates/bazar/, 
    // then in tools/XXX/templates/bazar/ 
    // and finally in tools/bazar/templates/
    private function addPath($path, $extensionName) {
        $this->paths[$extensionName][] = $path; // save the $path, will be used by renderPhp method
        $this->twigLoader->addPath($path, $extensionName);
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
        $method = endsWith($templatePath, '.twig') ? 'renderTwig' : 'renderPhp';
        return $this->$method($templatePath, $data);
    }

    public function renderTwig($templatePath, $data = [])
    {
        $data = array_merge($data, [
            'config' => $this->wiki->config,
            'request' => $_GET,
        ]);
        return $this->twig->render($templatePath, $data);
    }

    public function renderPhp($templatePath, $data = []) {
        preg_match("/^@([a-zA-Z0-9]+)\/(.*)$/", $templatePath, $matches);
        $realTemplatePath = null;
        // if templatePath is something like @extensionName/$fileName
        if (isset($matches[1])) {            
            $extensionName = $matches[1];
            $templateName = $matches[2];
            if (!$templateName) {
                throw new TemplateNotFound("You need to provide a file name. Path provided : $templatePath");
            }
            // Look for the template in the different paths
            $templatePath = null;
            foreach ($this->paths[$extensionName] as $path) {
                if (file_exists($path . $templateName)) {
                    $realTemplatePath = $path . $templateName;
                    break;
                }
            }
        } else {
            if (file_exists($templatePath)) $realTemplatePath = $templatePath;
        }        

        if ($realTemplatePath === null) {
            throw new TemplateNotFound(_t('TEMPLATE_FILE_NOT_FOUND'). " : $templateName in $extensionName");
        }

        if (!empty($data)) extract($data); // extract variables for the template

        ob_start(); // buffer
        include $realTemplatePath;
        $content = ob_get_contents(); // get buffer's content
        ob_end_clean(); // destroy buffer
        return $content;
    }
}
