<?php

namespace YesWiki\HelloWorld\Controller;

use Symfony\Component\HttpFoundation\Response;
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

    /**
     * @Route("/api/hello", name="api_helloworld_doc")
     */
    public function getDocumentation()
    {
        $output = $this->wiki->Header();

        $output .= '<h2>Extension Hello World</h2>';

        $urlHello = $this->wiki->Href('', 'api/hello/test');
        $urlHelloTest = $this->wiki->Href('', 'api/hello/{test}');
        $output .= 'The following code :<br />';
        $output .= 'GET <code>'.$urlHelloTest.'</code><br />';
        $output .= 'gives :<br />';
        $output .= '<code>test</code><br />Example : <br />';
        $output .= 'GET <code><a href="'.$urlHello.'">'.$urlHello.'</a></code><br />';

        $output .= $this->wiki->Footer();

        return new Response($output);
    }
}
