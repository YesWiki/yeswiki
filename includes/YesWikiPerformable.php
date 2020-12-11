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
    // Declared public so they are accessible in the concrete classes
    public $wiki;
    public $arguments = [];

    /**
     * Creates a YesWikiAction object associated with the given wiki object.
     */
    public function __construct(&$wiki)
    {
        $this->wiki = &$wiki;
        $this->twig = $this->wiki->services->get(TemplateEngine::class);
    }

    abstract public function run();

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
