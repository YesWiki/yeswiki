<?php

namespace YesWiki\Core;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use YesWiki\Core\Service\TemplateEngine;

abstract class YesWikiController implements ContainerAwareInterface
{
    protected $services;

    public function setContainer(ContainerInterface $services = null)
    {
        $this->services = $services;
    }

    public function render($templatePath, $data = [], $method = 'render')
    {
        return $this->services->get(TemplateEngine::class)->$method($templatePath, $data);
    }

    public function renderInSquelette($templatePath, $data = [])
    {
        return $this->render($templatePath, $data, 'renderInSquelette');
    }

    protected function getService($className)
    {
        return $this->services->get($className);
    }
}
