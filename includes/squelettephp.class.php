<?php
/**
 * SquelettePhp
 *
 * Original Author : Brian Lozier
 * Source : http://www.massassi.com/php/articles/template_engines/
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
     * Filename of template.
     *
     * @var string
     */
    protected $templateFile = '';

    /**
     * Path to template file.
     *
     * @var string
     */
    protected $templatePath = '';

    /**
     * Constructor
     *
     * @param string $templateFile Filename of template.
     * @param string $templateDir Directory of template type, used to find path to template file.
     */
    public function __construct($templateFile, $templateDir)
    {
        $found = false;
        $paths = [];
        // Collecting path possibilities
        foreach (['custom/templates', 'templates', 'themes/tools'] as $dir) {
            // XXX/bazar/templates/my-template.tpl.html
            $paths[] = $dir.'/'.$templateDir.'/templates/'.$templateFile;
            // XXX/bazar/my-template.tpl.html
            $paths[] = $dir.'/'.$templateDir.'/'.$templateFile;
            // XXX/bazar/my-template/my-template.tpl.html
            $paths[] = $dir.'/'.$templateDir.'/'.preg_replace('/.tpl.html$/Ui', '', $templateFile).'/'.$templateFile;
        }
        // default path
        $paths[] = 'tools/'.$templateDir.'/presentation/templates/'.$templateFile;
        
        // Look for the template in the different paths
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->templateFile = $templateFile;
                $this->templatePath = str_replace($templateFile, '', $path);
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new Exception(_t('TEMPLATE_FILE_NOT_FOUND').' : '.$templateFile);
        }
    }

    /**
     */
    /**
     * Add several or one value to template
     *
     * @param mixed $name variable name, or array name=>value
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
     * Replace variables in template file
     *
     * @param mixed $name variable name, or array name=>value
     * @param mixed $value value(s) used to render template.
     */
    public function render($name = '', $value = [])
    {
        if (!empty($name)) {
            $this->set($name, $value);
        }
        if (!empty($this->vars)) {
            extract($this->vars); // extract variables for the template
        }
        ob_start(); // buffer
        include realpath($this->templatePath.$this->templateFile); // include the template, with values
        $content = ob_get_contents(); // get buffer's content
        ob_end_clean(); // destroy buffer
        return $content; // Retourne le contenu
    }
}
