<?php

namespace YesWiki\Core;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Wiki;
use Doctrine\Common\Annotations\Annotation\Required;

abstract class YesWikiController
{
    protected $wiki;

    // auto injection of the Wiki instance
    /** @required */
    public function setWikiObject(Wiki $wiki)
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
