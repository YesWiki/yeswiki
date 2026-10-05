<?php

namespace YesWiki\Helloworld\Handler;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\PageContext;

/** `/PageName/hello`: the page's body in the extension's own template. */
class HelloHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'hello';
    }

    public function run(): string
    {
        $this->denyAccessUnlessGranted('read');

        $pageBody = ($this->getService(PageContext::class)->getPage() ?? [])['body'];

        return $this->renderFullPage('@helloworld/hello.twig', ['body' => $pageBody]);
    }
}
