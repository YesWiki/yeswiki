<?php

namespace YesWiki\Security\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Service\HashCashService;

class ApiController extends YesWikiController
{
    /**
     * Bootstrap script that inserts the hashcash hidden field into a form, then fetches the
     * puzzle from getHashcashKey() below. Replaces the old tools/security/wp-hashcash-js.php,
     * which - being a plain file under tools/ - can't be reached by URL on farm instances
     * (see src/bootstrap_paths.php: only the source tree has a tools/ folder on disk).
     */
    #[Route('/api/hashcash', methods: ['GET'], options: ['acl' => ['public']])]
    public function getHashcashScript(Request $request): Response
    {
        $formId = (string)($request->query->get('formid') ?: 'ACEditor');

        return new Response(
            $this->getService(HashCashService::class)->getEnableScript($formId),
            Response::HTTP_OK,
            ['Content-Type' => 'application/javascript']
        );
    }

    /**
     * The actual hashcash puzzle, fetched by the script above. Replaces the old
     * tools/security/wp-hashcash-getkey.php, same reasoning as getHashcashScript().
     */
    #[Route('/api/hashcash/key', methods: ['GET'], options: ['acl' => ['public']])]
    public function getHashcashKey(): Response
    {
        return new Response(
            $this->getService(HashCashService::class)->getKeyScript(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/javascript',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    /**
     * @param string $hashb64
     *
     * @throws \Exception if error
     */
    #[Route('/api/captcha/{hashb64}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getCaptcha($hashb64): StreamedResponse
    {
        // clean headers and cache
        if (!headers_sent()) {
            header_remove();
        }
        if (ob_get_level() > 1) {
            ob_end_clean();
        }
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers' => 'X-Requested-With, Location, Slug, Accept, Content-Type',
            'Access-Control-Expose-Headers' => 'Location, Slug, Accept, Content-Type',
            'Access-Control-Allow-Methods' => 'GET',
            'Cache-Control' => 'no-store, no-cache, must-revalidate', // HTTP/1.1
            'Content-Type' => 'Content-type: image/png',
        ];
        $hash = base64_decode($hashb64);

        return new StreamedResponse(
            function () use ($hash) {
                // callable only call when sending
                if (ob_get_level() > 1) {
                    ob_end_clean();
                }
                $this->getService(CaptchaController::class)->printImage($hash);
            },
            Response::HTTP_OK,
            $headers
        );
    }
}
