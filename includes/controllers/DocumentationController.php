<?php

namespace YesWiki\Core\Controller;

use YesWiki\Core\YesWikiController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DocumentationController extends YesWikiController
{
    /**
     * @Route("/doc",options={"acl":{"public"}})
     */
    public function show()
    {
      return new Response($this->render('@core/documentation.twig', [
        'config' => $this->wiki->config,
        'i18n' => json_encode($GLOBALS['translations_js']),
        'locale' => $GLOBALS['prefered_language']
      ]));
    }
}