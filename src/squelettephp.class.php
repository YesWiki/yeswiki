<?php

use YesWiki\Core\Service\TemplateEngine;

/**
 * @deprecated Use TemplateEngine render method instead
 */
class SquelettePhp
{
    /**
     * Variables which will be integrated in the template.
     *
     * @var array
     */
    protected $vars = [];

    /**
     * Path to template file.
     *
     * @var string
     */
    protected $templatePath = '';

    /**
     * Constructor.
     *
     * @param string $templateFile filename of template
     * @param string $templateDir  directory of template type, used to find path to template file
     */
    public function __construct($templateFile, $templateDir)
    {
        $this->templatePath = "@$templateDir/$templateFile";
    }

    /**
     * Add several or one value to template.
     *
     * @param mixed $name  variable name, or array name=>value
     * @param mixed $value value(s) of variable or SquelettePhp object to include
     */
    public function set($name, $value = [])
    {
        if (empty($value) && is_array($name)) {
            $this->vars = $name;
        } elseif ($value instanceof SquelettePhp) {
            $this->vars[$name] = $value->render();
        } else {
            $this->vars[$name] = $value;
        }
    }

    /**
     * Replace variables in template file.
     *
     * @param mixed $name  variable name, or array name=>value
     * @param mixed $value value(s) used to render template
     *
     * @deprecated Use TemplateEngine render method instead
     */
    public function render($name = '', $value = [])
    {
        if (!empty($name)) {
            $this->set($name, $value);
        }

        return $GLOBALS['wiki']->services->get(TemplateEngine::class)->render($this->templatePath, $this->vars);
    }
}
