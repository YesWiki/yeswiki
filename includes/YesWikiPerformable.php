<?php
namespace YesWiki\Core;

use YesWiki\Core\Service\TemplateEngine;

/**
 * A YesWiki object, with basic functionality like accessing main YesWiki instance, or
 * use easily templates
 * See Performer service which run such object
 */
abstract class YesWikiPerformable
{
    // Declared public so they are accessible in callbacks
    public $wiki;
    public $arguments = []; 
    public $output = ''; // string result for running the object
    /**
     * Creates a YesWikiAction object associated with the given wiki object.
     */
    public function __construct(&$wiki)
    {
        $this->wiki = &$wiki;
        $this->twig = $this->wiki->services->get(TemplateEngine::class);
    }

    /**
     * Performs an action asked by a user in a wiki page.
     * @param array $arguments An array containing the value of each parameter
     * given to the action, where the names of the parameters are the key,
     * corresponding to the given string value.
     * @example if a page contains {{include page="PageTag"}}
     * $arguments will be array('page' => 'PageTag');
     * @return string The result of the action
     */
    public function runWithCallbacks($arguments, $beforeCallbacks, $afterCallbacks)
    {
        $this->arguments = $arguments;
        foreach($beforeCallbacks as $callbackPath) {
            $this->output .= $this->performCallback($callbackPath);    
        }
        $this->output .= $this->run($arguments);
        foreach($afterCallbacks as $callbackPath) {
            $this->output .= $this->performCallback($callbackPath);
        }
        echo $this->output;
    }

    /**
     * See Performer Service for more explanation about callbacks
     */
    private function performCallback($callbackPath) {        
        require_once($callbackPath);
        $callbackName = basename($callbackPath, '.php');
        if (class_exists($callbackName)) {
            $callbackInstance = new $callbackName($this->wiki);
            return  $callbackInstance->run($this);
        } else {
            die("There were a problem while loading callback $callbackName at $callbackPath. Ensures the class exists");
        }
    }

    abstract public function run($arguments);

    /**
     * Shortcut to render twig template
     *
     * @param string $templatePath path to twig template. you can use full path
     * like tools/bazar/template/myfile.twig, or namespace like @bazar/myfile.twig
     * @param array $data An array with data to pass to the template
     * @return void
     */
    public function render($templatePath, $data = [], $method = 'render')
    {        
        $data = array_merge($data, ['arguments' => $this->arguments]);
        return $this->twig->$method($templatePath, $data);
    }

    public function renderInSquelette($templatePath, $data = [])
    {        
        return $this->render($templatePath, $data, 'renderInSquelette');
    }

    //  Shortcut to access services
    protected function getService($className) {
        return $this->wiki->services->get($className);
    }
}
