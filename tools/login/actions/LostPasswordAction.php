<?php

namespace YesWiki\Login;

use Exception;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Exception\BadFormatPasswordException;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Security\Controller\SecurityController;

if (!function_exists('send_mail')) {
    require_once('includes/email.inc.php');
}

class LostPasswordAction extends YesWikiAction
{
    private const PW_SALT = 'FBcA';
    public const KEY_VOCABULARY = 'http://outils-reseaux.org/_vocabulary/key';

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
                $user = $this->manageSubStep(filter_input(INPUT_POST, 'subStep', FILTER_SANITIZE_NUMBER_INT));
            } catch (Exception $ex) {
                $this->typeOfRendering = 'directDangerMessage';
                $this->errorType = 'exception';
                $message = $ex->getMessage();
            }
        } elseif (isset($_GET['a']) && $_GET['a'] === 'recover' && !empty($_GET['email'])) {
            $this->typeOfRendering = 'directDangerMessage';
            $message = _t('LOGIN_INVALID_KEY');
            $hash = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_STRING);
            $encodedUser = filter_input(INPUT_GET, 'u', FILTER_SANITIZE_STRING);
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
        $renderedTitle = '<h2>'._t('LOGIN_CHANGE_PASSWORD').'</h2>';
        switch ($this->typeOfRendering) {
            case 'userNotFound':
                return $renderedTitle.$this->render("@templates/alert-message-with-back.twig", [
                    'type' => 'danger',
                    'message' => _t('LOGIN_UNKNOWN_USER')
                ]);
                break;
            case 'successPage':
                return $renderedTitle.$this->render("@templates/alert-message.twig", [
                    'type' => 'success',
                    'message' => _t('LOGIN_MESSAGE_SENT')
                ]);
                break;
            case 'recoverSuccess':
                return $renderedTitle.$this->render("@templates/alert-message.twig", [
                    'type' => 'success',
                    'message' => _t('LOGIN_PASSWORD_WAS_RESET')
                ]);
                break;
            case 'recoverForm':
                return $this->render("@login/lost-password-recover-form.twig", [
                    'errorType' => $this->errorType,
                    'user' => $user,
                    'message' => $message ?? "",
                    'key' => $hash ?? filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING),
                    'inIframe' => (testUrlInIframe() == 'iframe')
                ]);
                break;
            case 'directDangerMessage':
                return $renderedTitle.$this->render("@templates/alert-message.twig", [
                    'type' => 'danger',
                    'message' => $message,
                ]);
                break;
            case 'emailForm':
            default:
                return $this->render("@login/lost-password-email-form.twig", [
                    'errorType' => $this->errorType,
                ]);
        }
    }

    /**
     * manage subStep
     * @param int $subStep
     * @throws Exception
     * @return null|User $user
     */
    private function manageSubStep(int $subStep): ?User
    {
        switch ($subStep) {
            case 1:
                // we just submitted an email or username for verification
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_STRING);
                if (empty($email)) {
                    $this->errorType = 'emptyEmail';
                    $this->typeOfRendering = 'emailForm';
                } else {
                    $user = $this->userManager->getOneByEmail($email);
                    if (!empty($user)) {
                        $this->typeOfRendering = 'successPage';
                        $this->sendPasswordRecoveryEmail($user);
                    } else {
                        $this->errorType = 'userNotFound';
                        $this->typeOfRendering = 'userNotFound';
                    }
                }
                break;
            case 2:
                // we are submitting a new password (only for encrypted)
                if (empty($_POST['userID']) || empty($_POST['key'])) {
                    $this->wiki->Redirect($this->wiki->Href("", $this->params->get('root_page')));
                }
                $userName = filter_input(INPUT_POST, 'userID', FILTER_SANITIZE_STRING);
                $user = $this->userManager->getOneByName($userName);
                $this->typeOfRendering = 'recoverForm';
                if (empty($_POST['pw0']) || empty($_POST['pw1']) || (strcmp($_POST['pw0'], $_POST['pw1']) != 0) || (trim($_POST['pw0']) == '')) {
                    // No pw0 or different pwd
                    $this->errorType = 'differentPasswords';
                } else {
                    if (!empty($user)) {
                        try {
                            $this->resetPassword(
                                $user['name'],
                                filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING),
                                filter_input(INPUT_POST, 'pw0', FILTER_SANITIZE_STRING)
                            );
                        } catch (BadFormatPasswordException $ex) {
                            $this->errorType = $ex->getMessage();
                            return $user;
                        }
                        $this->typeOfRendering = 'recoverSuccess';
                        // get $user a new time to have the new password
                        $user = $this->userManager->getOneByName($userName);
                        $this->userManager->login($user);
                    } else { // Not able to load the user from DB
                        $this->errorType = 'userNotFound';
                    }
                }
                break;
        }
        return $user ?? null;
    }

    /* Password recovery process (AKA reset password)
            1. A key is generated using name, email alongside with other stuff.
            2. The triple (user's name, specific key "vocabulary",key) is stored in triples table.
            3. In order to update h·er·is password, the user must provided that key.
            4. The new password is accepted only if the key matches with the value in triples table.
            5. The corresponding row is removed from triples table.
    */

    /** Part of the Password recovery process: Handles the password recovery email process
     *
     * Generates the password recovery key
     * Stores the (name, vocabulary, key) triple in triples table
     * Generates the recovery email
     * Sends it
     *
     * @param User $user
     * @return boolean True if OK or false if any problems
     */
    private function sendPasswordRecoveryEmail(User $user)
    {
        // Generate the password recovery key
        $key = md5($user['name'] . '_' . $user['email'] . rand(0, 10000) . date('Y-m-d H:i:s') . self::PW_SALT);
        // Erase the previous triples in the trible table
        $this->tripleStore->delete($user['name'], self::KEY_VOCABULARY, null, '', '') ;
        // Store the (name, vocabulary, key) triple in triples table
        $res = $this->tripleStore->create($user['name'], self::KEY_VOCABULARY, $key, '', '');

        // Generate the recovery email
        $passwordLink = $this->wiki->Href('', '', [
            'a' => 'recover',
            'email' => $key,
            'u' => base64_encode($user['name'])
        ], false);
        $pieces = parse_url($this->params->get('base_url'));
        $domain = isset($pieces['host']) ? $pieces['host'] : '';

        $message = _t('LOGIN_DEAR').' ' . $user['name'] . ",\n";
        $message .= _t('LOGIN_CLICK_FOLLOWING_LINK').' :' . "\n";
        $message .= '-----------------------' . "\n";
        $message .= $passwordLink . "\n";
        $message .= '-----------------------' . "\n";
        $message .= _t('LOGIN_THE_TEAM').' ' . $domain . "\n";

        $subject = _t('LOGIN_PASSWORD_LOST_FOR').' ' . $domain;
        // Send the email
        return send_mail($this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $this->params->get('BAZ_ADRESSE_MAIL_ADMIN'), $user['email'], $subject, $message);
    }

    /** Part of the Password recovery process: sets the password to a new value if given the the proper recovery key (sent in a recovery email).
     *
     * In order to update h·er·is password, the user provides a key (sent using sendPasswordRecoveryEmail())
     * The new password is accepted only if the key matches with the value in triples table.
     * The corresponding row is the removed from triples table.
     * See Password recovery process above
     *
     * @param string $userName The user login
     * @param string $key The password recovery key (sent by email)
     * @param string $pwd the new password value
     *
     * @return boolean True if OK or false if any problems
    */
    private function resetPassword(string $userName, string $key, string $password)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if ($this->checkEmailKey($key, $userName) === false) { // The password recovery key does not match
            throw new Exception(_t('USER_INCORRECT_PASSWORD_KEY').'.');
        }

        $user = $this->userManager->getOneByName($userName);
        if (empty($user)) {
            $this->error = false;
            $this->typeOfRendering = 'userNotFound';
            return null;
        }
        $this->authController->setPassword($user, $password);
        // Was able to update password => Remove the key from triples table
        $this->tripleStore->delete($user['name'], self::KEY_VOCABULARY, $key, '', '');
        return true;
    }

    /** Part of the Password recovery process: Checks the provided key against the value stored for the provided user in triples table
     *
     * As part of the Password recovery process, a key is generated and stored as part of a (user, $this->keyVocabulary, key) triple in the triples table. This function checks wether the key is right or not.
     * See Password recovery process above
     * replaces checkEmailKey($hash, $key) from login.functions.php
     *         TODO : Add error handling
     * @param string $hash The key to check
     * @param string $user The user for whom we check the key
     *
     * @return boolean True if success and false otherwise.
    */
    private function checkEmailKey(string $hash, string $user): bool
    {
        // Pas de detournement possible car utilisation de _vocabulary/key ....
        return !is_null($this->tripleStore->exist($user, self::KEY_VOCABULARY, $hash, '', ''));
    }
    /* End of Password recovery process (AKA reset password)   */
}
