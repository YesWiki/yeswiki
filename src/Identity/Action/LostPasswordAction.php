<?php

namespace YesWiki\Identity\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Exception\BadFormatPasswordException;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Kernel\Service\WikiUrls;

class LostPasswordAction extends YesWikiAction implements RegisteredAction
{
    /** `{{lostpassword}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'lostpassword';
    }

    protected AuthenticationService $authenticationService;
    protected ?string $errorType = null;
    protected string $typeOfRendering = 'emailForm';
    protected InputFilter $inputFilter;
    protected HibernationService $hibernationService;
    protected TripleStore $tripleStore;
    protected UserManager $userManager;

    public function run(): string
    {
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->inputFilter = $this->getService(InputFilter::class);
        $this->hibernationService = $this->getService(HibernationService::class);
        $this->tripleStore = $this->getService(TripleStore::class);
        $this->userManager = $this->getService(UserManager::class);

        $this->errorType = null;
        $this->typeOfRendering = 'emailForm';

        $message = '';
        $user = null;

        $request = $this->getRequest();
        if ($request->request->has('subStep') && !$request->query->has('a')) {
            try {
                $user = $this->manageSubStep(
                    $this->inputFilter->filterInput(INPUT_POST, 'subStep', FILTER_SANITIZE_NUMBER_INT, false, 'int')
                );
            } catch (\Exception $ex) {
                $this->typeOfRendering = 'directDangerMessage';
                $this->errorType = 'exception';
                $message = $ex->getMessage();
            }
        } elseif ($request->query->get('a') === 'recover' && !empty($request->query->get('email'))) {
            $this->typeOfRendering = 'directDangerMessage';
            $message = _t('LOGIN_INVALID_KEY');
            $hash = $this->inputFilter->filterInput(INPUT_GET, 'email', FILTER_DEFAULT, true);
            $encodedUser = $this->inputFilter->filterInput(INPUT_GET, 'u', FILTER_DEFAULT, true);
            if (empty($hash)) {
                $this->errorType = 'invalidKey';
            } elseif ($this->checkEmailKey($hash, base64_decode($encodedUser))) {
                $user = $this->userManager->getOneByName(base64_decode($encodedUser));
                if (empty($user)) {
                    $this->errorType = 'userNotFound';
                    $message = _t('LOGIN_UNKNOWN_USER');
                } else {
                    $this->typeOfRendering = 'recoverForm';
                }
            } else {
                $this->errorType = 'invalidKey';
            }
        }
        $renderedTitle = '<h2>' . _t('LOGIN_CHANGE_PASSWORD') . '</h2>';
        switch ($this->typeOfRendering) {
            case 'userNotFound':
                return $renderedTitle . $this->render('@core/alert-message-with-back.twig', [
                    'type' => 'danger',
                    'message' => _t('LOGIN_UNKNOWN_USER'),
                ]);
            case 'successPage':
                return $renderedTitle . $this->render('@core/alert-message.twig', [
                    'type' => 'success',
                    'message' => _t('LOGIN_MESSAGE_SENT'),
                ]);
            case 'recoverSuccess':
                return $renderedTitle . $this->render('@core/alert-message.twig', [
                    'type' => 'success',
                    'message' => _t('LOGIN_PASSWORD_WAS_RESET'),
                ]);
            case 'recoverForm':
                if (isset($hash)) {
                    $key = $hash;
                } else {
                    $key = $this->inputFilter->filterInput(INPUT_POST, 'key', FILTER_DEFAULT, true);
                }

                return $this->render('@core/lost-password-recover-form.twig', [
                    'errorType' => $this->errorType,
                    'user' => $user,
                    'message' => $message,
                    'key' => $hash ?? $key,
                    'inIframe' => (WikiUrls::iframeSuffixFor() == 'iframe'),
                ]);
            case 'directDangerMessage':
                return $renderedTitle . $this->render('@core/alert-message.twig', [
                    'type' => 'danger',
                    'message' => $message,
                ]);
            case 'emailForm':
            default:
                return $this->render('@core/lost-password-email-form.twig', [
                    'errorType' => $this->errorType,
                ]);
        }
    }

    /**
     * manage subStep.
     *
     * @return User|null $user
     *
     * @throws \Exception
     */
    private function manageSubStep(int $subStep): ?User
    {
        switch ($subStep) {
            case 1:
                $email = $this->inputFilter->filterInput(INPUT_POST, 'email', FILTER_DEFAULT, true);
                if (empty($email)) {
                    $this->errorType = 'emptyEmail';
                    $this->typeOfRendering = 'emailForm';
                } else {
                    $user = $this->userManager->getOneByEmail($email);
                    if (!empty($user)) {
                        $this->typeOfRendering = 'successPage';
                        $this->userManager->sendPasswordRecoveryEmail($user);
                    } else {
                        $this->errorType = 'userNotFound';
                        $this->typeOfRendering = 'userNotFound';
                    }
                }
                break;
            case 2:
                $post = $this->getRequest()->request;
                if (empty($post->get('userID')) || empty($post->get('key'))) {
                    $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', $this->params->get('root_page')));
                }
                $userName = $this->inputFilter->filterInput(INPUT_POST, 'userID', FILTER_DEFAULT, true);
                $user = $this->userManager->getOneByName($userName);
                $this->typeOfRendering = 'recoverForm';
                $submittedPassword = $post->get('pw0');
                $submittedConfirmation = $post->get('pw1');
                if (
                    empty($submittedPassword)
                    || empty($submittedConfirmation)
                    || strcmp(strval($submittedPassword), strval($submittedConfirmation)) != 0
                    || trim(strval($submittedPassword)) == ''
                ) {
                    $this->errorType = 'differentPasswords';
                } else {
                    if (!empty($user)) {
                        try {
                            $key = $this->inputFilter->filterInput(INPUT_POST, 'key', FILTER_DEFAULT, true);
                            $pw0 = $this->inputFilter->filterInput(INPUT_POST, 'pw0', FILTER_DEFAULT, true);
                            $this->resetPassword(
                                $user['name'],
                                $key,
                                $pw0
                            );
                        } catch (BadFormatPasswordException $ex) {
                            $this->errorType = $ex->getMessage();

                            return $user;
                        }
                        $this->typeOfRendering = 'recoverSuccess';

                        $user = $this->userManager->getOneByName($userName);
                        if ($user !== null) {
                            $this->authenticationService->login($user);
                        }
                    } else {
                        $this->errorType = 'userNotFound';
                    }
                }
                break;
        }

        return $user ?? null;
    }

    /**
     * In order to update h·er·is password, the user provides a key (sent using sendPasswordRecoveryEmail()) The new password is accepted only if the key matches with the value in triples table.
     *
     * @param string $userName The user login
     * @param string $key      The password recovery key (sent by email)
     *
     * @return bool True if OK or false if any problems
     *
     * @throws BadFormatPasswordException if $password doesn't meet the site's password policy
     */
    private function resetPassword(string $userName, string $key, string $password)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if ($this->checkEmailKey($key, $userName) === false) {
            throw new \Exception(_t('USER_INCORRECT_PASSWORD_KEY') . '.');
        }

        $user = $this->userManager->getOneByName($userName);
        if (empty($user)) {
            $this->typeOfRendering = 'userNotFound';

            return false;
        }

        $this->authenticationService->checkPasswordValidateRequirements($password);
        $this->authenticationService->setPassword($user, $password);

        $this->tripleStore->delete($user['name'], UserManager::KEY_VOCABULARY, null, '', '');

        return true;
    }

    /**
     * Part of the Password recovery process: Checks the provided key against the value stored for the provided user in triples table.
     *
     * @param string $hash The key to check
     * @param string $user The user for whom we check the key
     *
     * @return bool true if success and false otherwise
     */
    private function checkEmailKey(string $hash, string $user): bool
    {
        $storedValue = $this->tripleStore->getOne($user, UserManager::KEY_VOCABULARY, '', '');
        if (empty($storedValue)) {
            return false;
        }
        $parts = explode(UserManager::KEY_VALUE_SEPARATOR, $storedValue);
        if (count($parts) !== 2) {
            return false;
        }
        [$storedHash, $issuedAt] = $parts;
        if (!hash_equals($storedHash, $hash)) {
            return false;
        }

        return (time() - (int)$issuedAt) <= UserManager::KEY_TTL;
    }
}
