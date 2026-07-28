<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Core\YesWikiController;

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

        $this->wiki->SetPage($this->wiki->LoadPage($tag, isset($_REQUEST['time']) ? $_REQUEST['time'] : ''));
        $this->wiki->LogReferrer();

        ob_start();
        try {
            echo $this->wiki->Method($method);
        } catch (ExitException $th) {
            ob_end_clean();

            // matches Wiki::Run()'s old behavior: under CLI (tests), $wiki->exit() only had to
            // unwind the call stack without ending the process, so nothing was ever printed;
            // otherwise the exit message was the entire response body.
            return $this->toResponse($this->wiki->isCli() ? '' : $th->getMessage());
        }

        return $this->toResponse(ob_get_clean());
    }

    /**
     * Actions/handlers (Wiki::Redirect(), and many others) still redirect the old way: a raw
     * header('Location: ...') call. PHP itself would treat that as an implicit 302, but
     * Response::send() always writes its own status line afterwards, defaulting to 200 and
     * silently turning the redirect into a blank page (the Location header is sent, but
     * browsers only act on it for a 3xx status). Reflect any such already-sent header here so
     * the response we hand back actually is the redirect that already (in part) happened.
     */
    private function toResponse(string $content): Response
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) {
                return new Response('', Response::HTTP_FOUND, ['Location' => trim(substr($header, \strlen('Location:')))]);
            }
        }

        return new Response($content);
    }
}
