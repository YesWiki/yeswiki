<?php

namespace YesWiki\Identity\Api;

use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AccountActivationService;

class EmailActivationApiController extends YesWikiController
{
    #[Route('/api/emailactivation/{userId}/activate', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function activateUser($userId)
    {
        $this->denyAccessUnlessAdmin();
        $this->getService(AccountActivationService::class)->activate($userId, '', true);

        return new ApiResponse(null);
    }

    #[Route('/api/emailactivation/{userId}/inactivate', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function inactivateUser($userId)
    {
        $this->denyAccessUnlessAdmin();
        $this->getService(AccountActivationService::class)->inactivate($userId);

        return new ApiResponse(null);
    }
}
