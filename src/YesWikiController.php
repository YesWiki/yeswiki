<?php

namespace YesWiki\Core;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Service\Attribute\Required;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\TemplateEngine;
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

    protected function renderInSquelette($templatePath, $data = [])
    {
        return $this->render($templatePath, $data, 'renderInSquelette');
    }

    protected function denyAccessUnlessGranted($role, $tag)
    {
        if (!$this->getService(AclService::class)->hasAccess($role, $tag)) {
            throw new AccessDeniedHttpException();
        }
    }

    protected function denyAccessUnlessAdmin()
    {
        if (!$this->wiki->UserIsAdmin()) {
            throw new AccessDeniedHttpException();
        }
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    protected function getService($className)
    {
        return $this->wiki->services->get($className);
    }
}
