<?php

namespace YesWiki\Identity\Handler;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Exception\BadActivationKeyException;
use YesWiki\Identity\Exception\UserNameDoesNotExistException;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Performable\RegisteredHandler;

class EmailActivationHandler extends YesWikiHandler implements RegisteredHandler
{
    /** `/PageName/emailactivation` -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'emailactivation';
    }

    public function run(): string
    {
        $accountActivationService = $this->getService(AccountActivationService::class);
        $userManager = $this->getService(UserManager::class);

        try {
            $userName = $this->getRequiredGetParam('username');
            if (empty($userName)) {
                throw new UserNameDoesNotExistException(_t('ACCOUNTACTIVATION_BY_EMAIL_EMPTY_USERNAME'));
            }
            $key = $this->getRequiredGetParam('key');
            if (empty($key)) {
                throw new BadActivationKeyException(_t('ACCOUNTACTIVATION_BY_EMAIL_EMPTY_KEY'));
            }

            $currentUser = $userManager->getLoggedUser();
            if (
                !empty($currentUser['name'])
                && $userName == $currentUser['name']
                && $accountActivationService->isActivated($currentUser['name'])
            ) {
                return $this->renderFullPage('@core/alert-message.twig', [
                    'type' => 'success',
                    'message' => _t('ACCOUNTACTIVATION_BY_EMAIL_ALREADY_ACTIVATED'),
                ]);
            }

            $accountActivationService->activate($userName, $key);

            return $this->renderFullPage('@core/alert-message.twig', [
                'type' => 'primary',
                'message' => _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_SUCCESS'),
            ]);
        } catch (UserNameDoesNotExistException|BadActivationKeyException $th) {
            return $this->renderFullPage('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_ERROR', ['error' => $th->getMessage()]),
            ]);
        }
    }

    private function getRequiredGetParam(string $name): string
    {
        $value = filter_input(INPUT_GET, $name, FILTER_UNSAFE_RAW);

        return in_array($value, [false, null], true) ? '' : $value;
    }
}
