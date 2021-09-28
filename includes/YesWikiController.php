<?php

namespace YesWiki\Core;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Wiki;

abstract class YesWikiController
{
    protected $wiki;
    public $request;

    public function __construct()
    {
        $this->request = Request::createFromGlobals();
    }

    /**
     * Setter for the wiki property
     * @param Wiki $wiki
     * @Required set the auto-injection
     */
    public function setWiki(Wiki $wiki): void
    {
        $this->wiki = $wiki;
    }

    protected function render($templatePath, $data = [], $method = 'render')
    {
        return $this->wiki->services->get(TemplateEngine::class)->$method($templatePath, $data);
    }

    protected function renderInSquelette($templatePath, $data = [])
    {
        return $this->render($templatePath, $data, 'renderInSquelette');
    }

    protected function denyAccessUnlessAdmin()
    {
        if (!$this->wiki->UserIsAdmin()) {
            throw new AccessDeniedHttpException();
        }
    }

    protected function getService($className)
    {
        return $this->wiki->services->get($className);
    }
}
