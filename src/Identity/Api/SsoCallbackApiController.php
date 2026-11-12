<?php

namespace YesWiki\Identity\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Controller\RendersSignInOutcome;
use YesWiki\Identity\Service\SignIn\SignInFlow;

/** `?api/auth_sso/callback=`, the address Doryphore's loginsso registered at the provider: kept so a migrated provider needs no change on its side. */
class SsoCallbackApiController extends YesWikiController
{
    use RendersSignInOutcome;

    /** The address names no provider: the handshake does. */
    #[Route('/api/auth_sso/callback', methods: ['GET'], options: ['acl' => ['public']])]
    public function callback(): Response
    {
        return $this->renderSignInOutcome($this->getService(SignInFlow::class)->callback($this->getRequest(), null));
    }
}
