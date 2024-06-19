<?php

namespace YesWiki\Core;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use YesWiki\Wiki;

/**
 * Inspired from https://github.com/symfony/framework-bundle/blob/5.x/Controller/ControllerResolver.php.
 */
class YesWikiControllerResolver extends ControllerResolver
{
    protected $wiki;

    public function __construct(Wiki $wiki, LoggerInterface $logger = null)
    {
        parent::__construct($logger);

        $this->wiki = $wiki;
    }

    protected function instantiateController(string $class)
    {
        return $this->configureController(parent::instantiateController($class), $class);
    }

    private function configureController($controller, string $class)
    {
        if ($controller instanceof YesWikiController) {
            $controller->setWiki($this->wiki);
        }

        return $controller;
    }
}
