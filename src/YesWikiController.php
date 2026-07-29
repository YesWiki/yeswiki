<?php

namespace YesWiki\Core;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Service\Attribute\Required;
use YesWiki\Identity\Service\AclService;
use YesWiki\Render\Service\TemplateEngine;

abstract class YesWikiController
{
    protected ContainerInterface $services;

    /**
     * Setter for the service container (historic setWiki()).
     */
    #[Required]
    public function setServices(ContainerInterface $services): void
    {
        $this->services = $services;
    }

    protected function getRequest(): Request
    {
        return $this->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
    }

    protected function render($templatePath, $data = [], $method = 'render')
    {
        return $this->services->get(TemplateEngine::class)->$method($templatePath, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function renderFullPage(string $templatePath, array $data = []): string
    {
        return $this->render($templatePath, $data, 'renderFullPage');
    }

    protected function denyAccessUnlessGranted($role, $tag)
    {
        if (!$this->getService(AclService::class)->hasAccess($role, $tag)) {
            throw new AccessDeniedHttpException();
        }
    }

    protected function denyAccessUnlessAdmin()
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
            throw new AccessDeniedHttpException();
        }
    }

    /**
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
}
