<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Render\Service\BoostedNavigation;
use YesWiki\Render\Service\CoreAssets;

/**
 * Adapter so ordinary wiki tag/method pages (Performer-dispatched actions/handlers/formatters - unchanged, see Wiki::Method()) go through the same HttpKernel/event pipeline as the attribute-routed api/doc controllers (see Wiki::handleWithHttpKernel()), instead of the echo-straight-to-stdout dispatch this used to be.
 */
class LegacyPageController extends YesWikiController
{
    public function __invoke(Request $request): Response
    {
        $tag = $request->attributes->get('_tag');
        $method = $request->attributes->get('_method');

        $this->getService(PageContext::class)->assignPage($this->getService(PageManager::class)->getOne($tag, isset($_REQUEST['time']) ? $_REQUEST['time'] : ''));

        $boosted = $this->getService(BoostedNavigation::class);

        if ($boosted->isBoosted() && !$boosted->fingerprintMatches()) {
            return $boosted->fullLoadResponse();
        }

        $this->getService(CoreAssets::class)->register();

        ob_start();
        try {
            echo $this->getService(Performer::class)->run($method, 'handler', []);
        } catch (ExitException $th) {
            ob_end_clean();

            return $this->toResponse(\YesWiki\YesWikiKernel::isCli() ? '' : $th->getMessage(), $boosted);
        }

        return $this->toResponse((string)ob_get_clean(), $boosted);
    }

    /**
     * Actions/handlers (Wiki::Redirect(), and many others) still redirect the old way: a raw header('Location: ...') call.
     */
    private function toResponse(string $content, ?BoostedNavigation $boosted = null): Response
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) {
                $target = trim(substr($header, \strlen('Location:')));

                if ($boosted?->isBoosted()) {
                    return $boosted->fullLoadResponse($target);
                }

                return new Response('', Response::HTTP_FOUND, ['Location' => $target]);
            }
        }

        if ($boosted?->isBoosted() && !$boosted->hasRenderedAPage()) {
            return $boosted->fullLoadResponse();
        }

        return new Response($content);
    }
}
