<?php

use YesWiki\Core\Exception\BadActivationKeyException;
use YesWiki\Core\Exception\UserNameDoesNotExistException;
use YesWiki\Core\Service\AccountActivationService;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiHandler;

class EmailActivationHandler extends YesWikiHandler
{
    public function run()
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
                return $this->renderInSquelette('@core/alert-message.twig', [
                    'type' => 'success',
                    'message' => _t('ACCOUNTACTIVATION_BY_EMAIL_ALREADY_ACTIVATED'),
                ]);
            }

            $accountActivationService->activate($userName, $key);

            return $this->renderInSquelette('@core/alert-message.twig', [
                'type' => 'primary',
                'message' => _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_SUCCESS'),
            ]);
        } catch (UserNameDoesNotExistException | BadActivationKeyException $th) {
            return $this->renderInSquelette('@core/alert-message.twig', [
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
