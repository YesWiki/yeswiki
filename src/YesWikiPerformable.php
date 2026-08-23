<?php

namespace YesWiki\Core;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Performable\FormatsArguments;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\HibernationNotice;
use YesWiki\Render\Service\TemplateEngine;

/**
 * A YesWiki object, with basic functionality like accessing main YesWiki instance, or use easily templates See Performer service which run such object.
 */
abstract class YesWikiPerformable
{
    use FormatsArguments;

    protected ContainerInterface $services;
    protected ParameterBagInterface $params;
    protected TemplateEngine $twig;
    /** @var array<string, mixed> */
    protected $arguments = [];
    /** @var string */
    protected $output;

    /** Setter for the service container (historic setWiki()). */
    public function setServices(ContainerInterface $services): void
    {
        $this->services = $services;
    }

    /** Setter for the parameters. */
    public function setParams(ParameterBagInterface $params): void
    {
        $this->params = $params;
    }

    /** Setter for the twig property. */
    public function setTwig(TemplateEngine $twig): void
    {
        $this->twig = $twig;
    }

    /**
     * Setter for the arguments property.
     *
     * @param array<string, mixed> $arguments
     */
    public function setArguments(array &$arguments): void
    {
        $this->arguments = &$arguments;
        $formattedArguments = $this->formatArguments($arguments);
        $this->arguments = array_merge($arguments, $formattedArguments);
    }

    /** Setter for the output property. */
    public function setOutput(string &$output): void
    {
        $this->output = &$output;
    }

    /**
     * What this action or handler prints.
     *
     * @return string HTML
     */
    abstract public function run();

    /**
     * Shortcut to render twig template.
     *
     * @param string               $templatePath path to twig template. you can use full path
     *                                           like tools/bazar/template/myfile.twig, or namespace like @bazar/myfile.twig
     * @param array<string, mixed> $data         An array with data to pass to the template
     * @param string               $method       the TemplateEngine method to render with
     *
     * @return string HTML
     */
    public function render($templatePath, $data = [], $method = 'render')
    {
        $data = array_merge($data, ['arguments' => $this->arguments]);

        $vUserManager = $this->services->get(UserManager::class);
        $userName = (!isset($_SESSION['user']) || empty($_SESSION['user']['name'])) ? '' : $_SESSION['user']['name'];
        $this->twig->addGlobal('user', new class($vUserManager, $userName) {
            public string $name;
            private UserManager $userManager;
            private bool $entryResolved = false;
            /**
             * @var array<string, mixed>|null
             */
            private ?array $entry = null;

            public function __construct(UserManager $userManager, string $name)
            {
                $this->userManager = $userManager;
                $this->name = $name;
            }

            /**
             * @return array<string, mixed>
             */
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

    /**
     * Run another action from inside this one.
     *
     * @param array<string, mixed> $arguments
     */
    protected function callAction(string $action, $arguments = []): string
    {
        return $this->getService(ActionRunner::class)->action($action, $arguments);
    }

    protected function getRequest(): Request
    {
        return $this->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
    }

    /**
     * The arguments this performable was written with, normalised for its own use.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    protected function formatArguments($arguments)
    {
        return $arguments;
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
