<?php

namespace YesWiki\Test\Render;

use YesWiki\Kernel\Service\PageContext;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `{{redirect}}` fires for the page you asked for, and for nothing else.
 *
 * A bazar list, an `{{include}}` and a field's static render all point PageContext at the
 * Content they are rendering and put it back afterwards. The redirect action only checked
 * the *method*, which is still `show` throughout — so one entry carrying a redirect sent
 * the whole list somewhere else, and the list looked empty with nothing to explain it.
 * Found by giving the Pages form's content field wiki syntax, which is what makes a list
 * format (and therefore run) every page's markup.
 */
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

            // what a list does: point at the entry it is rendering, then put it back
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

    /**
     * Nothing outside a served page records a requested tag -- the CLI, the test harness,
     * a migration. Those must keep the old behaviour rather than silently stop redirecting.
     */
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
