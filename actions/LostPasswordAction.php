<?php

use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Exception\BadFormatPasswordException;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Core\YesWikiAction;

class LostPasswordAction extends YesWikiAction
{
    protected $authenticationService;
    protected $errorType;
    protected $typeOfRendering;
    protected $inputFilter;
    protected $hibernationService;
    protected $tripleStore;
    protected $userManager;

    public function run()
    {
        // get services
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->inputFilter = $this->getService(InputFilter::class);
        $this->hibernationService = $this->getService(HibernationService::class);
        $this->tripleStore = $this->getService(TripleStore::class);
        $this->userManager = $this->getService(UserManager::class);

        // init properties
        $this->errorType = null;
        $this->typeOfRendering = 'emailForm';

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
                    'message' => $message ?? '',
                    'key' => $hash ?? $key,
                    'inIframe' => (testUrlInIframe() == 'iframe'),
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
                // we just submitted an email or username for verification
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
                // we are submitting a new password (only for encrypted)
                $post = $this->getRequest()->request;
                if (empty($post->get('userID')) || empty($post->get('key'))) {
                    $this->wiki->Redirect($this->wiki->Href('', $this->params->get('root_page')));
                }
                $userName = $this->inputFilter->filterInput(INPUT_POST, 'userID', FILTER_DEFAULT, true);
                $user = $this->userManager->getOneByName($userName);
                $this->typeOfRendering = 'recoverForm';
                if (empty($post->get('pw0')) || empty($post->get('pw1')) || (strcmp($post->get('pw0'), $post->get('pw1')) != 0) || (trim($post->get('pw0')) == '')) {
                    // No pw0 or different pwd
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
                        // get $user a new time to have the new password
                        $user = $this->userManager->getOneByName($userName);
                        $this->authenticationService->login($user);
                    } else { // Not able to load the user from DB
                        $this->errorType = 'userNotFound';
                    }
                }
                break;
        }

        return $user ?? null;
    }

    /**
     * In order to update h·er·is password, the user provides a key (sent using sendPasswordRecoveryEmail())
     * The new password is accepted only if the key matches with the value in triples table.
     * The corresponding row is the removed from triples table.
     *
     * @param string $userName The user login
     * @param string $key      The password recovery key (sent by email)
     *
     * @throws BadFormatPasswordException if $password doesn't meet the site's password policy
     *
     * @return bool True if OK or false if any problems
     */
    private function resetPassword(string $userName, string $key, string $password)
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if ($this->checkEmailKey($key, $userName) === false) { // The password recovery key does not match
            throw new \Exception(_t('USER_INCORRECT_PASSWORD_KEY') . '.');
        }

        $user = $this->userManager->getOneByName($userName);
        if (empty($user)) {
            $this->typeOfRendering = 'userNotFound';

            return false;
        }
        // password-recovery was the one path to setting a password that never enforced the
        // site's strength policy, unlike signup and self-service change -- a backdoor around
        // it. checkPasswordValidateRequirements() throws BadFormatPasswordException, already
        // caught by manageSubStep()'s case 2 (the catch clause was already there, unreachable
        // until now).
        $this->authenticationService->checkPasswordValidateRequirements($password);
        $this->authenticationService->setPassword($user, $password);
        // Was able to update password => Remove the key from triples table
        // (only one active key per user, so no need to match the exact value)
        $this->tripleStore->delete($user['name'], UserManager::KEY_VOCABULARY, null, '', '');

        return true;
    }

    /** Part of the Password recovery process: Checks the provided key against the value stored for the provided user in triples table.
     *
     * As part of the Password recovery process, a key is generated and stored as part of a (user, $this->keyVocabulary, "key:issuedAt") triple
     * in the triples table. This function checks whether the key is right and still within its validity window.
     * See Password recovery process above
     * replaces checkEmailKey($hash, $key) from login.functions.php
     *         TODO : Add error handling
     *
     * @param string $hash The key to check
     * @param string $user The user for whom we check the key
     *
     * @return bool true if success and false otherwise
     */
    private function checkEmailKey(string $hash, string $user): bool
    {
        // Pas de detournement possible car utilisation de _vocabulary/key ....
        $storedValue = $this->tripleStore->getOne($user, UserManager::KEY_VOCABULARY, '', '');
        if (empty($storedValue)) {
            return false;
        }
        $parts = explode(UserManager::KEY_VALUE_SEPARATOR, $storedValue);
        if (count($parts) !== 2) {
            return false; // malformed or legacy (pre-expiry) value : reject
        }
        [$storedHash, $issuedAt] = $parts;
        if (!hash_equals($storedHash, $hash)) {
            return false;
        }

        return (time() - (int) $issuedAt) <= UserManager::KEY_TTL;
    }
}
