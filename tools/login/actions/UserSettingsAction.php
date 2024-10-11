<?php

namespace YesWiki\Login;

use Exception;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Throwable;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\Controller\UserController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\BadFormatPasswordException;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Exception\UserNameAlreadyUsedException;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Security\Controller\SecurityController;

class UserSettingsAction extends YesWikiAction
{
    private const ACTIONS = [
        'logout',
        'deleteByAdmin',
        'update',
        'updateByAdmin',
        'changepass',
        'signup',
        'checklogged',
    ];

    private $authController;
    private $csrfTokenController;
    private $securityController;
    private $userController;
    private $userManager;

    private $action;
    private $adminIsActing;
    private $error;
    private $errorUpdate;
    private $errorPasswordChange;
    private $userLoggedIn;
    private $referrer;
    private $wantedEmail;
    private $wantedUserName;
    private $userlink;

    public function formatArguments($arg)
    {
        return [];
    }

    public function run()
    {
        $this->getServices();

        // init vars
        $this->setActionFromRequest($_REQUEST ?? []);
        $this->error = '';
        $this->errorUpdate = '';
        $this->errorPasswordChange = '';
        $this->referrer = '';
        $user = $this->getUser($_GET ?? []);
        if (!boolval($this->wiki->config['contact_disable_email_for_password']) && !empty($user)) {
            $this->userlink = $this->userManager->getLastUserLink($user);
        } else {
            $this->userlink = '';
        }

        $this->doPrerenderingActions($_POST ?? [], $user);

        return $this->displayForm($user);
    }

    private function getServices()
    {
        $this->authController = $this->getService(AuthController::class);
        $this->csrfTokenController = $this->getService(CsrfTokenController::class);
        $this->securityController = $this->getService(SecurityController::class);
        $this->userController = $this->getService(UserController::class);
        $this->userManager = $this->getService(UserManager::class);
    }

    private function setActionFromRequest(array $request)
    {
        $notTrustedAction = $request['usersettings_action'] ?? '';
        $this->action = in_array($notTrustedAction, self::ACTIONS, true) ? $notTrustedAction : '';
    }

    private function getUser(array $get): ?User
    {
        $this->adminIsActing = false;
        $this->userLoggedIn = false;
        $this->wantedUserName = htmlspecialchars($get['user'] ?? '');
        $this->wantedEmail = filter_var($get['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $user = null;
        if ($this->wiki->UserIsAdmin() && (
            !empty($this->wantedUserName)
            ||
            !empty($this->wantedEmail)
        )) {
            if (!empty($this->wantedUserName)) {
                $this->adminIsActing = true;
                $user = $this->userManager->getOneByName($this->wantedUserName);
                if (empty($user)) { // Did not find the user in DB
                    $this->wiki->SetMessage(_t('USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER') . ' !');
                }
                $this->referrer = filter_var($get['from'] ?? '', FILTER_SANITIZE_URL);
            } elseif (!empty($this->wantedEmail)) {
                $this->adminIsActing = true;

                $user = $this->userManager->getOneByEmail($this->wantedEmail); // In this case we need to load the right user

                if (empty($user)) { // Did not find the user in DB
                    $this->wiki->SetMessage(_t('USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER') . ' !');
                }
            }
        } else {
            $userFromSession = $this->authController->getLoggedUser();
            $user = isset($userFromSession['name']) ? $this->userManager->getOneByName($userFromSession['name']) : null;
            if ($user) { // Trying to instanciate $user from the session cooky)
                $this->userLoggedIn = true;
            }
        }

        return $user;
    }

    private function doPrerenderingActions(array $post, ?User &$user = null)
    {
        switch ($this->action) {
            case 'logout':
                $this->logout();
                break;
            case 'deleteByAdmin':
                $this->deleteByAdmin($user);
                break;
            case 'update':
            case 'updateByAdmin':
                $this->update($post, $user);
                break;
            case 'changepass':
                $this->changePassword($user, $post);
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

    private function displayForm(?User $user = null)
    {
        if ($this->adminIsActing || $this->userLoggedIn) {
            return $this->render('@login/usersettings.twig', [
                'adminIsActing' => $this->adminIsActing,
                'errorPasswordChange' => $this->errorPasswordChange,
                'errorUpdate' => $this->errorUpdate,
                'inIframe' => testUrlInIframe() == 'iframe',
                'referrer' => $this->referrer,
                'user' => $user,
                'userLoggedIn' => $this->userLoggedIn,
                'userlink' => $this->userlink
            ]);
        } else {
            $captcha = $this->securityController->renderCaptchaField();
            $captcha = preg_replace('/(' .
                preg_quote('<div class="media-body">', '/') .
                "\s*" .
                preg_quote('<strong>', '/') .
                ')[^<]*(' .
                preg_quote('</strong>', '/') .
                ')/', '$1' . _t('USERSETTINGS_CAPTCHA_USER_CREATION') . '$2', $captcha);
            // this file is kept to manage custom user-signup-form.tpl.html that will not been used if use directly .twig
            // TODO remove the .tpl.html for ectoplasme and use directly .twig
            return $this->render('@login/user-signup-form.tpl.html', [
                'link' => $this->wiki->href(), // notice 'link' is not used in .twig TODO remove this line for ectoplasme
                'namesToExport' => ['error', 'name', 'email', 'captcha'], // TOTO remove this line when removing .tpl.html
                'error' => $this->error,
                'name' => $this->wantedUserName,
                'email' => $this->wantedEmail,
                'captcha' => $captcha,
                'userlink' => ''
            ]);
        }
    }

    private function logout()
    {
        // User wants to log out
        $this->authController->logout();
        $this->wiki->SetMessage(_t('USER_YOU_ARE_NOW_DISCONNECTED') . ' !');
        $this->wiki->Redirect($this->wiki->href());
    }

    private function deleteByAdmin(?User &$user = null)
    {
        if ($this->adminIsActing && !empty($this->wantedUserName)) {
            // Admin trying to delete user
            try {
                $this->csrfTokenController->checkToken('main', 'POST', 'csrf-token-delete', false);
                if (empty($user)) {
                    $this->errorUpdate = _t('USERSETTINGS_USER_NOT_DELETED') . ' user not found';

                    return null;
                }
                $this->userController->delete($user);
                $user = null;
                // forward
                $this->wiki->SetMessage(_t('USER_DELETED') . ' !');
                $this->wiki->Redirect($this->wiki->href('', $this->referrer));
            } catch (TokenNotFoundException $th) {
                $this->errorUpdate = _t('USERSETTINGS_USER_NOT_DELETED') . ' ' . $th->getMessage();
            }
        }
    }

    private function update(array $post, User $user)
    {
        if ($this->adminIsActing || $this->userLoggedIn) {
            try {
                $this->csrfTokenController->checkToken('main', 'POST', 'csrf-token-update', false);

                $sanitizedPost = array_map(function ($item) {
                    return is_scalar($item) ? $item : '';
                }, $post);

                $this->userController->update(
                    $user,
                    $sanitizedPost
                );
                $this->userlink = '';
                if (!boolval($this->wiki->config['contact_disable_email_for_password'])) {
                    if ($this->userManager->sendPasswordRecoveryEmail($user, _t('LOGIN_PASSWORD_FOR'))) {
                        $this->userlink = $this->userManager->getUserLink();
                    }
                }

                $user = $this->userManager->getOneByEmail($sanitizedPost['email']);

                if (!empty($user)) {
                    if ($this->userLoggedIn) { // In case it's the user trying to update oneself, need to reset the cookies
                        $this->authController->login($user);
                    }
                    // forward
                    $this->wiki->SetMessage(_t('USER_PARAMETERS_SAVED') . ' !');
                    if ($this->userLoggedIn) { // In case it's the usther trying to update oneself
                        $this->wiki->Redirect($this->wiki->href());
                    } else { // That's the admin acting, we need to pass the user on
                        $this->wiki->Redirect($this->wiki->href('', '', 'user=' . $this->wantedUserName . '&from=' . $this->referrer, false));
                    }
                } else { // Unable to update
                    throw new Exception('');
                }
            } catch (TokenNotFoundException $th) {
                $this->errorUpdate = _t('USERSETTINGS_EMAIL_NOT_CHANGED') . ' ' . $th->getMessage();
            } catch (UserEmailAlreadyUsedException $th) {
                $email = isset($post['email']) && is_string($post['email']) ? htmlspecialchars($post['email']) : '';
                $this->errorUpdate = _t('USERSETTINGS_EMAIL_NOT_CHANGED') . ' ' . str_replace('{email}', $email, _t('USERSETTINGS_EMAIL_ALREADY_USED'));
            } catch (Exception $th) {
                // TODO use a specific exception
                $this->errorUpdate = _t('USERSETTINGS_EMAIL_NOT_CHANGED') . ' ' . $th->getMessage();
            }
        }
    }

    private function changePassword(?User $user, array $post)
    {
        if ($this->userLoggedIn) {
            // User wants to change password
            if (!$this->authController->checkPassword($post['oldpass'], $user)) { // check password first
                $this->errorPasswordChange = _t('USER_WRONG_PASSWORD') . ' !';
            } else { // user properly typed his old password in
                // check token
                try {
                    $this->csrfTokenController->checkToken('main', 'POST', 'csrf-token-changepass', false);

                    $password = $post['password'];
                    $this->authController->setPassword($user, $password);
                    $this->wiki->SetMessage(_t('USER_PASSWORD_CHANGED') . ' !');
                    // reload $user
                    $user = $this->userManager->getOneByName($user['name']);
                    if (!empty($user)) {
                        $this->authController->login($user);
                    }
                    $this->wiki->Redirect($this->wiki->href());
                } catch (TokenNotFoundException $th) {
                    $this->errorPasswordChange = _t('USERSETTINGS_PASSWORD_NOT_CHANGED') . ' ' . $th->getMessage();
                } catch (BadFormatPasswordException | Throwable $ex) {
                    // Something when wrong when updating the user in DB
                    $this->errorPasswordChange = _t('USERSETTINGS_PASSWORD_NOT_CHANGED') . ' ' . $ex->getMessage();
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
            $emptyInputsParametersNames = array_filter(['email', 'name', 'password', 'confpassword'], function ($key) use ($post) {
                return empty($post[$key]);
            });
            try {
                $password = isset($post['password']) && is_string($post['password']) ? $post['password'] : '';
                if (!empty($emptyInputsParametersNames)) {
                    $this->error = str_replace('{parameters}', implode(',', $emptyInputsParametersNames), _t('USERSETTINGS_SIGNUP_MISSING_INPUT'));
                } elseif (
                    $this->authController->checkPasswordValidateRequirements($password) &&
                    $post['confpassword'] !== $password
                ) {
                    $this->error = _t('USER_PASSWORDS_NOT_IDENTICAL') . '.';
                } else { // Password is correct
                    $_POST['submit'] = SecurityController::EDIT_PAGE_SUBMIT_VALUE;
                    list($state, $error) = $this->securityController->checkCaptchaBeforeSave();
                    if (!$state) {
                        $this->error = $error;
                    } else {
                        $user = $this->userController->create([
                            'changescount' => 100,
                            'doubleclickedit' => 'Y',
                            'email' => $post['email'] ?? '',
                            'name' => $post['name'] ?? '',
                            'password' => $password,
                            'revisioncount' => 20,
                            'show_comments' => 'N',
                        ]);
                        if (!empty($user)) {
                            $this->authController->login($user);
                            $this->wiki->Redirect($this->wiki->href()); // forward
                        }
                        $this->error = _t('USER_CREATION_FAILED') . '.';
                    }
                }
            } catch (BadFormatPasswordException $ex) {
                $this->error = $ex->getMessage();
            } catch (UserNameAlreadyUsedException $ex) {
                $this->error = str_replace('{currentName}', strval($post['name']), _t('USERSETTINGS_NAME_ALREADY_USED'));
            } catch (UserEmailAlreadyUsedException $ex) {
                $this->error = str_replace('{email}', strval($post['email']), _t('USERSETTINGS_EMAIL_ALREADY_USED'));
            } catch (ExitException $ex) {
                throw $ex;
            } catch (Exception $ex) {
                $this->error = $ex->getMessage();
            }
        }
    }

    private function checklogged(array $post)
    {
        $this->error = _t('USER_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED') . '.';
    }
}