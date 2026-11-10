<?php

namespace YesWiki\Identity\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\Service\SignIn\SignInFlow;
use YesWiki\Identity\Service\SignIn\Type\LocalType;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\SignInOutcome;

class AuthApiController extends YesWikiController
{
    /**
     * Attempt to login a user (ticket 08, relocated from tools/login's own ApiController), through the local provider like the form: the same attempt limit applies.
     *
     * @return string json
     */
    #[Route('/api/login', methods: ['POST'], options: ['acl' => ['public']])]
    public function login()
    {
        if (!$this->getService(ProviderRegistry::class)->localEnabled()) {
            return new ApiResponse(['error' => _t('SIGN_IN_REFUSED_PROVIDER_DISABLED', ['provider' => _t('SIGN_IN_LOCAL_LABEL')])], Response::HTTP_FORBIDDEN);
        }

        $post = $this->getRequest()->request;
        $outcome = $this->getService(SignInFlow::class)->password(
            LocalType::NAME,
            (string)$post->get('username', ''),
            (string)$post->get('password', ''),
            false
        );

        if ($outcome->kind === SignInOutcome::SIGNED_IN) {
            return new ApiResponse([
                'user' => $this->getService(AuthenticationService::class)->signedInName(),
                'isAdmin' => $this->getService(AclService::class)->isAdmin(),
            ]);
        }
        if ($outcome->reason === FailureReason::TooManyAttempts) {
            return new ApiResponse(['error' => FailureReason::TooManyAttempts->value, 'message' => $outcome->messageForPerson()], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return new ApiResponse(
            ['error' => $outcome->kind === SignInOutcome::FORM_ERROR ? $outcome->message : $outcome->messageForPerson()],
            match ($outcome->reason) {
                FailureReason::ProviderDisabled => Response::HTTP_FORBIDDEN,
                FailureReason::InternalError => Response::HTTP_INTERNAL_SERVER_ERROR,
                default => Response::HTTP_UNAUTHORIZED,
            }
        );
    }

    /**
     * Return basic information if the current user is authenticated.
     *
     * @return string json
     */
    #[Route('/api/auth/me', options: ['acl' => ['public']])]
    public function getMyAuth()
    {
        $loggedUser = $this->getService(AuthenticationService::class)->getLoggedUser();
        if (!$loggedUser) {
            return new ApiResponse(['error' => _t('LOGIN_NO_CONNECTED_USER')], Response::HTTP_UNAUTHORIZED);
        }

        return new ApiResponse([
            'user' => $loggedUser['name'],
            'isAdmin' => $this->getService(AclService::class)->isAdmin(),
        ]);
    }
}
