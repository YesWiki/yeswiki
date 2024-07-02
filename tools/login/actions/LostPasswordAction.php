<?php

namespace YesWiki\Login;

use Exception;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\BadFormatPasswordException;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Security\Controller\SecurityController;

class LostPasswordAction extends YesWikiAction
{

    protected $authController;
    protected $errorType;
    protected $typeOfRendering;
    protected $securityController;
    protected $tripleStore;
    protected $userManager;

    public function run()
    {
        // get services
        $this->authController = $this->getService(AuthController::class);
        $this->securityController = $this->getService(SecurityController::class);
        $this->tripleStore = $this->getService(TripleStore::class);
        $this->userManager = $this->getService(UserManager::class);

        // init properties
        $this->errorType = null;
        $this->typeOfRendering = 'emailForm';

        if (isset($_POST['subStep']) && !isset($_GET['a'])) {
            try {
                $user = $this->manageSubStep(
                    $this->securityController->filterInput(INPUT_POST, 'subStep', FILTER_SANITIZE_NUMBER_INT, false, 'int')
                );
            } catch (Exception $ex) {
                $this->typeOfRendering = 'directDangerMessage';
                $this->errorType = 'exception';
                $message = $ex->getMessage();
            }
        } elseif (isset($_GET['a']) && $_GET['a'] === 'recover' && !empty($_GET['email'])) {
            $this->typeOfRendering = 'directDangerMessage';
            $message = _t('LOGIN_INVALID_KEY');
            $hash = $this->securityController->filterInput(INPUT_GET, 'email', FILTER_DEFAULT, true);
            $encodedUser = $this->securityController->filterInput(INPUT_GET, 'u', FILTER_DEFAULT, true);
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
                return $renderedTitle . $this->render('@templates/alert-message-with-back.twig', [
                    'type' => 'danger',
                    'message' => _t('LOGIN_UNKNOWN_USER'),
                ]);
                break;
            case 'successPage':
                return $renderedTitle . $this->render('@templates/alert-message.twig', [
                    'type' => 'success',
                    'message' => _t('LOGIN_MESSAGE_SENT'),
                ]);
                break;
            case 'recoverSuccess':
                return $renderedTitle . $this->render('@templates/alert-message.twig', [
                    'type' => 'success',
                    'message' => _t('LOGIN_PASSWORD_WAS_RESET'),
                ]);
                break;
            case 'recoverForm':
                if (isset($hash)) {
                    $key = $hash;
                } else {
                    $key = $this->securityController->filterInput(INPUT_POST, 'key', FILTER_DEFAULT, true);
                }

                return $this->render('@login/lost-password-recover-form.twig', [
                    'errorType' => $this->errorType,
                    'user' => $user,
                    'message' => $message ?? '',
                    'key' => $hash ?? $key,
                    'inIframe' => (testUrlInIframe() == 'iframe'),
                ]);
                break;
            case 'directDangerMessage':
                return $renderedTitle . $this->render('@templates/alert-message.twig', [
                    'type' => 'danger',
                    'message' => $message,
                ]);
                break;
            case 'emailForm':
            default:
                return $this->render('@login/lost-password-email-form.twig', [
                    'errorType' => $this->errorType,
                ]);
        }
    }

    /**
     * manage subStep.
     *
     * @throws Exception
     *
     * @return User|null $user
     */
    private function manageSubStep(int $subStep): ?User
    {
        switch ($subStep) {
            case 1:
                // we just submitted an email or username for verification
                $email = $this->securityController->filterInput(INPUT_POST, 'email', FILTER_DEFAULT, true);
                if (empty($email)) {
                    $this->errorType = 'emptyEmail';
                    $this->typeOfRendering = 'emailForm';
                } else {
                    $user = $this->userManager->getOneByEmail($email);
                    if (!empty($user)) {
                        $this->typeOfRendering = 'successPage';
                        $this->userManager->sendPasswordRecoveryEmail($user, _t('LOGIN_PASSWORD_LOST_FOR'));
                    } else {
                        $this->errorType = 'userNotFound';
                        $this->typeOfRendering = 'userNotFound';
                    }
                }
                break;
            case 2:
                // we are submitting a new password (only for encrypted)
                if (empty($_POST['userID']) || empty($_POST['key'])) {
                    $this->wiki->Redirect($this->wiki->Href('', $this->params->get('root_page')));
                }
                $userName = $this->securityController->filterInput(INPUT_POST, 'userID', FILTER_DEFAULT, true);
                $user = $this->userManager->getOneByName($userName);
                $this->typeOfRendering = 'recoverForm';
                if (empty($_POST['pw0']) || empty($_POST['pw1']) || (strcmp($_POST['pw0'], $_POST['pw1']) != 0) || (trim($_POST['pw0']) == '')) {
                    // No pw0 or different pwd
                    $this->errorType = 'differentPasswords';
                } else {
                    if (!empty($user)) {
                        try {
                            $key = $this->securityController->filterInput(INPUT_POST, 'key', FILTER_DEFAULT, true);
                            $pw0 = $this->securityController->filterInput(INPUT_POST, 'pw0', FILTER_DEFAULT, true);
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
                        $this->authController->login($user);
                    } else { // Not able to load the user from DB
                        $this->errorType = 'userNotFound';
                    }
                }
                break;
        }

        return $user ?? null;
    }

    /** Part of the Password recovery process: sets the password to a new value if given the the proper recovery key (sent in a recovery email).
     *
     * In order to update h·er·is password, the user provides a key (sent using sendPasswordRecoveryEmail())
     * The new password is accepted only if the key matches with the value in triples table.
     * The corresponding row is the removed from triples table.
     * See Password recovery process above
     *
     * @param string $userName The user login
     * @param string $key      The password recovery key (sent by email)
     * @param string $pwd      the new password value
     *
     * @return bool True if OK or false if any problems
     */
    private function resetPassword(string $userName, string $key, string $password)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if ($this->checkEmailKey($key, $userName) === false) { // The password recovery key does not match
            throw new Exception(_t('USER_INCORRECT_PASSWORD_KEY') . '.');
        }

        $user = $this->userManager->getOneByName($userName);
        if (empty($user)) {
            $this->error = false;
            $this->typeOfRendering = 'userNotFound';

            return null;
        }
        $this->authController->setPassword($user, $password);
        // Was able to update password => Remove the key from triples table
        $this->tripleStore->delete($user['name'], UserManager::KEY_VOCABULARY, $key, '', '');

        return true;
    }

    /** Part of the Password recovery process: Checks the provided key against the value stored for the provided user in triples table.
     *
     * As part of the Password recovery process, a key is generated and stored as part of a (user, $this->keyVocabulary, key) triple in the triples table. This function checks wether the key is right or not.
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
        return !is_null($this->tripleStore->exist($user, UserManager::KEY_VOCABULARY, $hash, '', ''));
    }
    /* End of Password recovery process (AKA reset password)   */
}
