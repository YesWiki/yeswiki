<?php
namespace YesWiki\Core;

use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Wiki;

/**
 * A YesWiki object, with basic functionality like accessing main YesWiki instance, or
 * use easily templates
 * See Performer service which run such object
 */
abstract class YesWikiPerformable
{
    protected $wiki;
    protected $twig;
    protected $arguments = [];
    protected $output;

    /**
     * Setter for wiki property
     * @param Wiki $wiki
     */
    public function setWiki(Wiki $wiki): void
    {
        $this->wiki = $wiki;
    }

    /**
     * Setter for twig property
     * @param TemplateEngine $twig
     */
    public function setTwig(TemplateEngine $twig): void
    {
        $this->twig = $twig;
    }

    /**
     * Setter for arguments property
     * @param array $arguments
     */
    public function setArguments(array &$arguments): void
    {
        $this->arguments = $arguments;
    }

    /**
     * Setter for output property
     * @param string $output
     */
    public function setOutput(string &$output): void
    {
        $this->output = $output;
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
