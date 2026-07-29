<?php

namespace YesWiki\Identity\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\HashCashService;

class HashcashApiController extends YesWikiController
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
}
