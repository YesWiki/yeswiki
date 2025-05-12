<?php

namespace YesWiki\Core;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

/**
 * A YesWiki object, with basic functionality like accessing main YesWiki instance, or
 * use easily templates
 * See Performer service which run such object.
 */
abstract class YesWikiPerformable
{
    protected $wiki;
    protected $params;
    protected $twig;
    protected $arguments = [];
    protected $output;

    /**
     * Setter for the wiki property.
     */
    public function setWiki(Wiki $wiki): void
    {
        $this->wiki = $wiki;
    }

    /**
     * Setter for the parameters.
     */
    public function setParams(ParameterBagInterface $params): void
    {
        $this->params = $params;
    }

    /**
     * Setter for the twig property.
     */
    public function setTwig(TemplateEngine $twig): void
    {
        $this->twig = $twig;
    }

    /**
     * Setter for the arguments property.
     */
    public function setArguments(array &$arguments): void
    {
        $this->arguments = &$arguments; // passed by ref to be able to change arguments in pre and post actions
        $formattedArguments = $this->formatArguments($arguments);
        $this->arguments = array_merge($arguments, $formattedArguments); // not array_merge return a copy not ref
    }

    /**
     * Setter for the output property.
     */
    public function setOutput(string &$output): void
    {
        $this->output = &$output;
    }

    abstract public function run();

    /**
     * Shortcut to render twig template.
     *
     * @param string $templatePath path to twig template. you can use full path
     *                             like tools/bazar/template/myfile.twig, or namespace like @bazar/myfile.twig
     * @param array  $data         An array with data to pass to the template
     *
     * @return string HTML
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
    protected function getService($className)
    {
        return $this->wiki->services->get($className);
    }

    // Shortcut to call an action within another action
    protected function callAction(string $action, $arguments = []): string
    {
        // This additional argument helps to prevent infinite loops
        $arguments['calledBy'] = get_class($this);

        return $this->wiki->Action($action, 0, $arguments);
    }

    protected function getRequest(): Request
    {
        return $this->wiki->request;
    }

    // Can be extended to format the arguments
    protected function formatArguments($arguments)
    {
        return $arguments;
    }

    protected function formatBoolean($param, $default = true, string $index = '')
    {
        if (is_array($param)) {
            if ($index != '' && isset($param[$index])) {
                $param = $param[$index];
            } else {
                return $default;
            }
        }
        if (is_bool($param)) {
            return $param;
        } elseif (in_array($param, [0, '0', 'no', 'non', 'false'], true)) {
            return false;
        } elseif (empty($param)) {
            return $default;
        } else {
            return true;
        }
    }

    protected function formatArray($param)
    {
        if (is_array($param)) {
            return $param;
        } else {
            return !empty($param) ? array_map('trim', explode(',', $param)) : [];
        }
    }

    /**
     * check if wiki_status is hibernated.
     *
     * @return bool true if in hibernation
     */
    protected function isWikiHibernated(): bool
    {
        return $this->wiki->services->get(SecurityController::class)->isWikiHibernated();
    }

    /**
     * return alert message when in hibernation.
     *
     * @return string true if in hibernation
     */
    protected function getMessageWhenHibernated(): string
    {
        return $this->wiki->services->get(SecurityController::class)->getMessageWhenHibernated();
    }
}
