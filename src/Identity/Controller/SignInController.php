<?php

namespace YesWiki\Identity\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Action\LoginAction;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\Service\SignIn\SignInFlow;
use YesWiki\Identity\SignIn\Contract\PublishesMetadata;

/** `/user/sign-in/{id}` -- the round trip to a provider. */
class SignInController extends YesWikiController
{
    use RendersSignInOutcome;

    #[Route('/user/sign-in/{id}', methods: ['GET'], options: ['acl' => ['public']])]
    public function begin(string $id): Response
    {
        $return = $this->getRequest()->query->all()[LoginAction::RETURN_PARAM] ?? '';

        return $this->renderSignInOutcome($this->getService(SignInFlow::class)->begin($id, is_string($return) ? $return : ''));
    }

    /** A cross-site POST is expected here, and RequestGuard lets it through: the signed handshake is this route's protection. */
    #[Route('/user/sign-in/{id}/callback', methods: ['GET', 'POST'], options: ['acl' => ['public']])]
    public function callback(string $id): Response
    {
        return $this->renderSignInOutcome($this->getService(SignInFlow::class)->callback($this->getRequest(), $id));
    }

    #[Route('/user/sign-in/{id}/metadata', methods: ['GET'], options: ['acl' => ['public']])]
    public function metadata(string $id): Response
    {
        $provider = $this->getService(ProviderRegistry::class)->get($id);

        return $provider instanceof PublishesMetadata ? $provider->metadataResponse() : new Response('', Response::HTTP_NOT_FOUND);
    }
}
