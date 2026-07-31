<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\ReferrerService;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Render\Service\BoostedNavigation;
use YesWiki\Render\Service\CoreAssets;

/**
 * Adapter so ordinary wiki tag/method pages (Performer-dispatched actions/handlers/formatters -
 * unchanged, see Wiki::Method()) go through the same HttpKernel/event pipeline as the
 * attribute-routed api/doc controllers (see Wiki::handleWithHttpKernel()), instead of the
 * echo-straight-to-stdout dispatch this used to be. Set as the _controller for every request that
 * isn't api/doc - see Wiki::Run().
 */
class LegacyPageController extends YesWikiController
{
    public function __invoke(Request $request): Response
    {
        $tag = $request->attributes->get('_tag');
        $method = $request->attributes->get('_method');

        $this->getService(PageContext::class)->assignPage($this->getService(PageManager::class)->getOne($tag, isset($_REQUEST['time']) ? $_REQUEST['time'] : ''));
        $this->getService(ReferrerService::class)->log();

        $boosted = $this->getService(BoostedNavigation::class);

        // Ticket 16: a page built from a different skeleton cannot be swapped into this one --
        // the chrome, the stylesheets and <html lang> would all be wrong. Checked before the
        // handler runs, so nothing is rendered only to be thrown away.
        if ($boosted->isBoosted() && !$boosted->fingerprintMatches()) {
            return $boosted->fullLoadResponse();
        }

        // ticket 15: core, theme and custom/ assets are declared before the handler renders
        // anything, so they lead the emitted set. The head is rendered last now, so a
        // registration made from the layout -- or from here, after the handler -- would be
        // too late to be ordered first.
        $this->getService(CoreAssets::class)->register();

        ob_start();
        try {
            echo $this->getService(Performer::class)->run($method, 'handler', []);
        } catch (ExitException $th) {
            ob_end_clean();

            // matches Wiki::Run()'s old behavior: under CLI (tests), $wiki->services->get(\YesWiki\Kernel\Service\Redirector::class)->terminate() only had to
            // unwind the call stack without ending the process, so nothing was ever printed;
            // otherwise the exit message was the entire response body.
            return $this->toResponse(\YesWiki\YesWikiKernel::isCli() ? '' : $th->getMessage(), $boosted);
        }

        return $this->toResponse((string)ob_get_clean(), $boosted);
    }

    /**
     * Actions/handlers (Wiki::Redirect(), and many others) still redirect the old way: a raw
     * header('Location: ...') call. PHP itself would treat that as an implicit 302, but
     * Response::send() always writes its own status line afterwards, defaulting to 200 and
     * silently turning the redirect into a blank page (the Location header is sent, but
     * browsers only act on it for a 3xx status). Reflect any such already-sent header here so
     * the response we hand back actually is the redirect that already (in part) happened.
     */
    private function toResponse(string $content, ?BoostedNavigation $boosted = null): Response
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) {
                $target = trim(substr($header, \strlen('Location:')));

                // A boosted request would follow the redirect inside the XHR and swap the
                // target's body while the address bar still showed the original URL. Hand the
                // redirect to the browser instead (ticket 16).
                if ($boosted?->isBoosted()) {
                    return $boosted->fullLoadResponse($target);
                }

                return new Response('', Response::HTTP_FOUND, ['Location' => $target]);
            }
        }

        // Ticket 16's one rule: only a response that went through renderPage() may be swapped.
        // A /raw handler, an error page, a bare-document terminate() -- anything else -- gets a
        // real navigation, so the address bar matches what is on screen and htmx never swaps
        // text/plain into the body.
        if ($boosted?->isBoosted() && !$boosted->hasRenderedAPage()) {
            return $boosted->fullLoadResponse();
        }

        return new Response($content);
    }
}
