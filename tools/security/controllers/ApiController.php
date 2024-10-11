<?php

namespace YesWiki\Security\Controller;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/captcha/{hashb64}", methods={"GET"}, options={"acl":{"public"}})
     *
     * @param string $hashb64
     *
     * @throws Exception if error
     */
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
