<?php

namespace YesWiki\Test\Render;

use YesWiki\Kernel\Service\PageContext;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `{{redirect}}` fires for the page you asked for, and for nothing else. */
class EmbeddedRedirectTest extends YesWikiTestCase
{
    public function testTheRequestedPageIsTheOneBeingRendered(): void
    {
        $pageContext = $this->getWiki()->services->get(PageContext::class);
        $before = ['tag' => $pageContext->getTag(), 'requested' => $pageContext->getRequestedTag()];

        try {
            $pageContext->setRequestedTag('PagePrincipale');
            $pageContext->setTag('PagePrincipale');
            $this->assertTrue($pageContext->isRenderingRequestedPage());

            $pageContext->setTag('UneFicheQuelconque');
            $this->assertFalse(
                $pageContext->isRenderingRequestedPage(),
                'an entry rendered inside a list is not the page the request asked for'
            );

            $pageContext->setTag('PagePrincipale');
            $this->assertTrue($pageContext->isRenderingRequestedPage());
        } finally {
            $pageContext->setTag($before['tag']);
            $pageContext->setRequestedTag($before['requested'] ?: null);
        }
    }

    /** Nothing outside a served page records a requested tag -- the CLI, the test harness, a migration. */
    public function testWithNoRequestedTagEverythingCountsAsTheRequestedPage(): void
    {
        $pageContext = $this->getWiki()->services->get(PageContext::class);
        $before = ['tag' => $pageContext->getTag(), 'requested' => $pageContext->getRequestedTag()];

        try {
            $pageContext->setRequestedTag(null);
            $pageContext->setTag('NImporteQuoi');
            $this->assertTrue($pageContext->isRenderingRequestedPage());
        } finally {
            $pageContext->setTag($before['tag']);
            $pageContext->setRequestedTag($before['requested'] ?: null);
        }
    }
}
