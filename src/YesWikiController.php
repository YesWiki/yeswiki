<?php

namespace YesWiki\Core;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Service\Attribute\Required;
use YesWiki\Identity\Service\AclService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Wiki;

abstract class YesWikiController
{
    protected $wiki;

    /**
     * Setter for the wiki property.
     */
    #[Required]
    public function setWiki(Wiki $wiki): void
    {
        $this->wiki = $wiki;
    }

    protected function getRequest(): Request
    {
        return $this->wiki->request;
    }

    protected function render($templatePath, $data = [], $method = 'render')
    {
        return $this->wiki->services->get(TemplateEngine::class)->$method($templatePath, $data);
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
        return $this->wiki->services->get($className);
    }
}
