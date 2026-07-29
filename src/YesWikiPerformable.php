<?php

namespace YesWiki\Core;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\HibernationNotice;
use YesWiki\Render\Service\TemplateEngine;

/**
 * A YesWiki object, with basic functionality like accessing main YesWiki instance, or
 * use easily templates
 * See Performer service which run such object.
 */
abstract class YesWikiPerformable
{
    protected ContainerInterface $services;
    protected $params;
    protected $twig;
    protected $arguments = [];
    protected $output;

    /**
     * Setter for the service container (historic setWiki()).
     */
    public function setServices(ContainerInterface $services): void
    {
        $this->services = $services;
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

        // add some addition globals
        $vUserManager = $this->services->get(UserManager::class);
        $userName = (!isset($_SESSION['user']) || empty($_SESSION['user']['name'])) ? '' : $_SESSION['user']['name'];
        $this->twig->addGlobal('user', new class($vUserManager, $userName) {
            public string $name;
            private UserManager $userManager;
            private bool $entryResolved = false;
            /** @var array<mixed>|null */
            private ?array $entry = null;

            public function __construct(UserManager $userManager, string $name)
            {
                $this->userManager = $userManager;
                $this->name = $name;
            }

            /** @return array<mixed> */
            public function getEntry(): array
            {
                if (!$this->entryResolved) {
                    $this->entry = $this->userManager->getAssociatedEntry() ?? [];
                    $this->entryResolved = true;
                }

                return $this->entry ?? [];
            }
        });

        return $this->twig->$method($templatePath, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function renderFullPage(string $templatePath, array $data = []): string
    {
        return $this->render($templatePath, $data, 'renderFullPage');
    }

    /**
     * Shortcut to access services.
     *
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T
     */
    protected function getService($className)
    {
        return $this->services->get($className);
    }

    // Shortcut to call an action within another action
    protected function callAction(string $action, $arguments = []): string
    {
        // This additional argument helps to prevent infinite loops.
        //
        // Deliberately the SHORT class name, not get_class(): callers compare it against
        // literals like 'BazarCartoAction', and once ticket 06 gave these classes namespaces
        // get_class() started returning the FQCN, so every such comparison silently stopped
        // matching -- turning {{bazarcarto}} into unbounded recursion. Third time this wave
        // that get_class() broke on namespacing (after the action name and the ACL key).
        $arguments['calledBy'] = (new \ReflectionClass($this))->getShortName();

        return $this->getService(ActionRunner::class)->action($action, false, $arguments);
    }

    protected function getRequest(): Request
    {
        return $this->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
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
        }

        return true;
    }

    protected function formatArray($param)
    {
        if (is_array($param)) {
            return $param;
        }

        return !empty($param) ? array_map('trim', explode(',', $param)) : [];
    }

    /**
     * check if wiki_status is hibernated.
     *
     * @return bool true if in hibernation
     */
    protected function isWikiHibernated(): bool
    {
        return $this->services->get(HibernationService::class)->isWikiHibernated();
    }

    /**
     * return alert message when in hibernation.
     *
     * @return string true if in hibernation
     */
    protected function getMessageWhenHibernated(): string
    {
        return $this->services->get(HibernationNotice::class)->getMessageWhenHibernated();
    }
}
