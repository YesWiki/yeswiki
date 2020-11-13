<?php
namespace YesWiki\Core;

use YesWiki\Core\Service\TemplateEngine;
/**
 * This class should be extended by each class that reprensents a WikiNi action.
 */
class YesWikiAction
{
    public $wiki;

    /**
     * Creates a YesWikiAction object associated with the given
     * wiki object.
     */
    public function __construct(&$wiki)
    {
        $this->wiki = &$wiki;
        $this->twig = $this->wiki->services->get(TemplateEngine::class);
    }

    /**
     * Performs an action asked by a user in a wiki page.
     * @param array $argz An array containing the value of each parameter
     * given to the action, where the names of the parameters are the key,
     * corresponding to the given string value.
     * @param string $command The full command which was in the page
     * between "{{" and "}}". This allow you to develop actions that do
     * not use the conventionnal syntax in the 'param="value"' format.
     * @example if a page contains
     * 	{{include page="PageTag"}}
     * $arguments will be array('page' => 'PageTag');
     * $command wil be 'include page="PageTag"'
     * @return string The result of the action
     */
    public function performAction($arguments, $command)
    {
        return '';
    }

    /**
     * Shortcut to render twig template
     *
     * @param string $templatePath path to twig template. you can use full path
     * like tools/bazar/template/myfile.twig, or namespace like @bazar/myfile.twig
     * @param array $data An array with data to pass to the template
     * @return void
     */
    public function render($templatePath, $data = [])
    {        
        $data = array_merge($data, ['arguments' => $this->arguments]);
        echo $this->twig->render($templatePath, $data);
    }

    /**
     * @return string The default ACL for this action (usually '*', '+' or '@'.ADMIN_GROUP)
     */
    public function getDefaultACL()
    {
        return '*';
    }
}

/**
 * This class is intended to be extended by each administration action.
 *
 * This will help access rights management. Currently its only particularity is to have a its
 * default ACL set to @admins.
 */
class YesWikiAdminAction extends YesWikiAction
{
    public function getDefaultACL()
    {
        return '@'.ADMIN_GROUP;
    }
}
