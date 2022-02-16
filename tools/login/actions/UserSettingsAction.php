<?php

namespace YesWiki\Login;

use Exception;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiAction;

class UserSettingsAction extends YesWikiAction
{
    private const ACTIONS = [
        "logout",
        "deleteByAdmin",
        "update",
        "updateByAdmin",
        "changepass",
        "signup",
        "checklogged"
    ];

    private $csrfTokenController;
    private $userManager;

    private $action;
    private $adminIsActing;
    private $error;
    private $errorUpdate;
    private $errorPasswordChange;
    private $userLoggedIn;
    private $referrer;
    private $wantedEmail ;
    private $wantedUserName ;

    public function formatArguments($arg)
    {
        return [];
    }

    public function run()
    {
        $this->getServices();

        // init vars
        $this->setActionFromRequest($_REQUEST ?? []);
        $this->error = "";
        $this->errorUpdate = "";
        $this->errorPasswordChange = "";
        $this->referrer = '';
        $this->setUser($_GET ?? []) ;

        $this->doPrerenderingActions($_POST ?? []);
        return $this->displayForm();
    }

    private function getServices()
    {
        $this->csrfTokenController = $this->getService(CsrfTokenController::class);
        $this->userManager = $this->getService(UserManager::class);
    }

    private function setActionFromRequest(array $request)
    {
        $notTrustedAction = $request['usersettings_action'] ?? "";
        $this->action = in_array($notTrustedAction, self::ACTIONS, true) ? $notTrustedAction : "";
    }

    private function setUser(array $get)
    {
        $this->adminIsActing = false;
        $this->userLoggedIn = false;
        $this->wantedUserName = htmlspecialchars($get['user'] ?? '');
        $this->wantedEmail = filter_var($get['email'] ?? '', FILTER_SANITIZE_EMAIL);
        if ($this->wiki->UserIsAdmin() && (
            !empty($this->wantedUserName)
            ||
            !empty($this->wantedEmail)
        )) {
            if (!empty($this->wantedUserName)) {
                $this->adminIsActing = true;
                $OK = $this->wiki->user->loadByNameFromDB($this->wantedUserName);
                if (!$OK) { // Did not find the user in DB
                    $this->wiki->session->setMessage(_t('USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER').' !');
                }
                $this->referrer = filter_var($get['from'] ?? '', FILTER_SANITIZE_URL);
            } elseif (!empty($this->wantedEmail)) {
                $this->adminIsActing = true;
                
                $OK = $this->wiki->user->loadByEmailFromDB($this->wantedEmail); // In this case we need to load the right user
                if (!$OK) { // Did not find the user in DB
                    $this->wiki->session->setMessage(_t('USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER').' !');
                }
            }
        } else {
            if ($this->wiki->user->loadFromSession()) { // Trying to instanciate $user from the session cooky)
                $this->userLoggedIn = true;
            }
        }
    }

    private function doPrerenderingActions(array $post)
    {
        switch ($this->action) {
            case 'logout':
                $this->logout();
                break;
            case 'deleteByAdmin':
                $this->deleteByAdmin();
                break;
            case 'update':
            case 'updateByAdmin':
                $this->update($post);
                break;
            case 'changepass':
                $this->changePassword($post);
                break;
            case 'checklogged':
                $this->checklogged($post);
                break;
            case 'signup':
                $this->signup($post);
                // no break
            default:
                $this->retrieveUsernameAndEmailFromPost($post);
                break;
        }
    }

    private function displayForm()
    {
        if ($this->adminIsActing || $this->userLoggedIn) {
            return $this->render("@login/usersettings.twig", [
                'adminIsActing' => $this->adminIsActing,
                'errorPasswordChange' => $this->errorPasswordChange,
                'errorUpdate' => $this->errorUpdate,
                'referrer' => $this->referrer,
                'user' => $this->wiki->user,
                'userLoggedIn' => $this->userLoggedIn
            ]);
        } else {
            // this file is kept to manage custom user-signup-form.tpl.html that will not been used if use directly .twig
            // TODO remove the .tpl.html for ectoplasme and use directly .twig
            return $this->render("@login/user-signup-form.tpl.html", [
                "link" => $this->wiki->href(), // notice 'link' is not used in .twig TODO remove this line for ectoplasme
                "error" => $this->error,
                "name" => $this->wantedUserName,
                "email" => $this->wantedEmail
            ]);
        }
    }

    private function logout()
    {
        // User wants to log out
        $this->wiki->user->logOut();
        $this->wiki->session->setMessage(_t('USER_YOU_ARE_NOW_DISCONNECTED').' !');
        $this->wiki->Redirect($this->wiki->href());
    }

    private function deleteByAdmin()
    {
        if ($this->adminIsActing && !empty($this->wantedUserName)) {
            // Admin trying to delete user
            try {
                $this->csrfTokenController->checkToken("login\action\usersettings\deleteByAdmin\\{$this->wantedUserName}", 'POST', 'csrf-token-delete');
                $this->wiki->user->delete();
                // forward
                $this->wiki->session->setMessage(_t('USER_DELETED').' !');
                $this->wiki->Redirect($this->wiki->href('', $this->referrer));
            } catch (TokenNotFoundException $th) {
                $this->errorUpdate = _t('USERSETTINGS_USER_NOT_DELETED') .' '. $th->getMessage();
            }
        }
    }

    private function update(array $post)
    {
        if ($this->adminIsActing || $this->userLoggedIn) {
            try {
                $this->csrfTokenController->checkToken('login\action\usersettings\updateuser', 'POST', 'csrf-token-update');

                $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
                if (empty($email)) {
                    throw new Exception(_t('USER_THIS_IS_NOT_A_VALID_EMAIL'));
                }
                // check if e-mail is already used
                $user = $this->userManager->getOneByEmail($email);
                if (!empty($user)) {
                    throw new Exception(_t('BAZ_USER_FIELD_EXISTING_USER_BY_EMAIL'));
                }
    
                $OK = $this->wiki->user->setByAssociativeArray([
                    'email'	 			=> $post['email'] ?? '',
                    'motto'				=> $post['motto'] ?? '',
                    'revisioncount'  	=> $post['revisioncount'] ?? '',
                    'changescount'		=> $post['changescount'] ?? '',
                    'doubleclickedit'	=> $post['doubleclickedit'] ?? '',
                    'show_comments' 	=> $post['show_comments'] ?? '',
                ]);
                if ($OK) {
                    $OK = $this->wiki->user->updateIntoDB('email, motto, revisioncount, changescount, doubleclickedit, show_comments');
                    if ($this->userLoggedIn) { // In case it's the user trying to update oneself, need to reset the cooky
                        $this->wiki->user->logIn();
                    }
                    // forward
                    $this->wiki->session->setMessage(_t('USER_PARAMETERS_SAVED').' !');
                    if ($this->userLoggedIn) { // In case it's the usther trying to update oneself
                        $this->wiki->Redirect($this->wiki->href());
                    } else { // That's the admin acting, we need to pass the user on
                        $this->wiki->Redirect($this->href('', '', 'user='.$this->wantedUserName.'&from='.$this->referrer, false));
                    }
                } else { // Unable to update
                    $this->wiki->session->setMessage($this->wiki->user->error);
                }
            } catch (TokenNotFoundException $th) {
                $this->errorUpdate = _t('USERSETTINGS_EMAIL_NOT_CHANGED') .' '. $th->getMessage();
            } catch (Exception $th) {
                // TODO use a specific exception
                $this->errorUpdate = _t('USERSETTINGS_EMAIL_NOT_CHANGED') .' '. $th->getMessage();
            }
        }
    }

    private function changePassword(array $post)
    {
        if ($this->userLoggedIn) {
            // User wants to change password
            if (!$this->wiki->user->checkPassword($post['oldpass'])) { // check password first
                $this->errorPasswordChange = $this->wiki->user->error;
            } else { // user properly typed his old password in
                // check token
                try {
                    $this->csrfTokenController->checkToken('login\action\usersettings\changepass', 'POST', 'csrf-token-changepass');

                    $password = $post['password'];
                    if ($this->wiki->user->updatePassword($password)) {
                        $this->wiki->session->setMessage(_t('USER_PASSWORD_CHANGED').' !');
                        $this->wiki->user->logIn();
                        $this->wiki->Redirect($this->wiki->href());
                    } else { // Something when wrong when updating the user in DB
                        $this->wiki->session->setMessage($this->wiki->user->error);
                    }
                } catch (TokenNotFoundException $th) {
                    $this->errorPasswordChange = _t('USERSETTINGS_PASSWORD_NOT_CHANGED') .' '. $th->getMessage();
                }
            }
        }
    }

    private function retrieveUsernameAndEmailFromPost(array $post)
    {
        if (!$this->adminIsActing && !$this->userLoggedIn) {
            $this->wantedEmail = filter_var($post['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $this->wantedUserName = htmlspecialchars($post['name'] ?? '');
        }
    }

    private function signup(array $post)
    {
        if (!$this->adminIsActing && !$this->userLoggedIn) {
            $emptyInputsParametersNames = array_filter(['email','name','password','confpassword'], function ($key) use ($post) {
                return empty($post[$key]);
            });
            if (!empty($emptyInputsParametersNames)) {
                $this->error = str_replace('{parameters}', implode(',', $emptyInputsParametersNames), _t('LOGIN_SIGNUP_MISSING_INPUT'));
            } elseif (!$this->wiki->user->passwordIsCorrect($post['password'], $post['confpassword'])) {
                $this->error = $this->wiki->user->error;
            } else { // Password is correct
                if ($this->wiki->user->setByAssociativeArray(
                    [
                        'name'				=> trim($post['name']),
                        'email'				=> trim($post['email']),
                        'password'			=> md5($post['password']),
                        'revisioncount'	    => 20,
                        'changescount'		=> 100,
                        'doubleclickedit'	=> 'Y',
                        'show_comments'	    => 'N',
                    ]
                )) { // User properties set without any problem
                    if ($this->wiki->user->createIntoDB()) { // No problem with user creation in DB
                        $this->wiki->user->logIn();
                        $this->wiki->Redirect($this->wiki->href()); // forward
                    } else { // PB while creating user in DB
                        $this->error = $this->wiki->user->error;
                    }
                } else { // We had problems with the properties setting
                    $wiki->error = $this->wiki->user->error;
                }
            }
        }
    }

    private function checklogged(array $post)
    {
        $this->error = _t('USER_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED').'.';
    }
}
