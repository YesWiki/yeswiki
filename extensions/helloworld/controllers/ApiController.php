<?php

namespace YesWiki\HelloWorld\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\UrlFormatter;

class ApiController extends YesWikiController
{
    #[Route('/api/hello/{name}', options: ['acl' => ['public']])]
    public function sayHello(Request $request, $name)
    {
        $action = $request->get('action') ?? 'hello';

        return new ApiResponse([$action => $name]);
    }

    #[Route('/api/hello', options: ['acl' => ['public']])]
    public function onlineDoc()
    {
        $output = $this->getDocumentation();
        $output = $this->getService(\YesWiki\Render\Service\TemplateEngine::class)->renderPage($output);

        return new Response($output);
    }

    /**
     * Display helloworld api documentation.
     *
     * @return string
     */
    public function getDocumentation()
    {
        $output = '<h2>Extension Hello World</h2>';

        $urlHello = $this->getService(UrlFormatter::class)->href('', 'api/hello/test');
        $urlHelloTest = $this->getService(UrlFormatter::class)->href('', 'api/hello/{test}');
        $output .= 'The following code :<br />';
        $output .= 'GET <code>' . $urlHelloTest . '</code><br />';
        $output .= 'gives :<br />';
        $output .= '<code>test</code><br />Example : <br />';
        $output .= 'GET <code><a href="' . $urlHello . '">' . $urlHello . '</a></code><br />';

        return $output;
    }
}
