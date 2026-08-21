<?php

namespace YesWiki\Core;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;

/**
 * Inspired from https://github.com/symfony/framework-bundle/blob/5.x/Controller/ControllerResolver.php.
 */
class YesWikiControllerResolver extends ControllerResolver
{
    protected ContainerInterface $services;

    public function __construct(ContainerInterface $services, ?LoggerInterface $logger = null)
    {
        parent::__construct($logger);

        $this->services = $services;
    }

    protected function instantiateController(string $class): object
    {
        return $this->configureController(parent::instantiateController($class), $class);
    }

    private function configureController(object $controller, string $class): object
    {
        if ($controller instanceof YesWikiController) {
            $controller->setServices($this->services);
        }

        return $controller;
    }
}
