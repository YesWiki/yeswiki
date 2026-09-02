<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\BoostedNavigation;
use YesWiki\Render\Service\CoreAssets;
use YesWiki\Render\Service\Performer;
use YesWiki\Render\Service\ThemeManager;

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

        $bazar = $this->retiredBazarPage((string)$tag, $request);
        if ($bazar !== null) {
            return $bazar;
        }

        $this->getService(CoreAssets::class)->register();

        ob_start();
        try {
            echo $this->getService(Performer::class)->run($method, 'handler', []);
        } catch (ExitException $th) {
            ob_end_clean();

            return $this->toResponse(\YesWiki\Core\YesWikiKernel::isCli() ? '' : $th->getMessage(), $boosted);
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

    /**
     * `BazaR` is no page any more: bare, it goes to the dashboard; with a bazar view in the query, the links the wiki still carries (an entry form in an iframe, a search) are still answered.
     */
    private function retiredBazarPage(string $tag, Request $request): ?Response
    {
        if (strcasecmp($tag, 'BazaR') !== 0 || $this->getService(PageManager::class)->getOne($tag) !== null) {
            return null;
        }
        $query = $request->query;
        if ($query->has('view') || $query->has('action') || $query->has('id') || $query->has('form_id')) {
            $this->getService(CoreAssets::class)->register();
            $content = $this->getService(ActionRunner::class)->action('bazar', ['showmenu' => '0']);

            return $this->toResponse($this->getService(ThemeManager::class)->renderPage((string)$content), $this->getService(BoostedNavigation::class));
        }

        return new Response('', Response::HTTP_FOUND, ['Location' => $this->getService(UrlFormatter::class)->href('', 'dashboard', ['view' => 'formulaire'], false)]);
    }
}
