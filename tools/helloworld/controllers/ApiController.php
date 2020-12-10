<?php

namespace YesWiki\HelloWorld\Controller;

use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/hello/{name}")
     */
    public function sayHello($name)
    {
        return new ApiResponse(['hello' => $name]);
    }
}
