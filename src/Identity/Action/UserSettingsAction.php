<?php

namespace YesWiki\Identity\Action;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Controller\CaptchaController;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Exception\BadFormatPasswordException;
use YesWiki\Identity\Exception\UserEmailAlreadyUsedException;
use YesWiki\Identity\Exception\UserNameAlreadyUsedException;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;

class UserSettingsAction extends YesWikiAction implements RegisteredAction
{
    /** `{{usersettings}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'usersettings';
    }

    private const ACTIONS = [
        'logout',
        'deleteByAdmin',
        'update',
        'updateByAdmin',
        'changepass',
        'signup',
        'checklogged',
        'resetpass',
    ];

    private $authenticationService;
    private $captchaController;
    private $csrfTokenChecker;
    private $userOperationsService;
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

    public function formatArguments($arg)
    {
        return [];
    }

    public function run()
    {
        $this->getServices();

        // init vars
        $request = $this->getRequest();
        $this->setActionFromRequest($request->query->all() + $request->request->all());
        $this->error = '';
        $this->errorUpdate = '';
        $this->errorPasswordChange = '';
        $this->referrer = '';
        $user = $this->getUser($request->query->all());

        $this->doPrerenderingActions($request->request->all(), $user);

        return $this->displayForm($user);
    }

    private function getServices()
    {
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->csrfTokenChecker = $this->getService(CsrfTokenChecker::class);
        $this->captchaController = $this->getService(CaptchaController::class);
        $this->userOperationsService = $this->getService(UserOperationsService::class);
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
        if ($this->getService(AclService::class)->isAdmin() && (
            !empty($this->wantedUserName)
            || !empty($this->wantedEmail)
        )) {
            if (!empty($this->wantedUserName)) {
                $this->adminIsActing = true;
                $user = $this->userManager->getOneByName($this->wantedUserName);
                if (empty($user)) { // Did not find the user in DB
                    $this->getService(FlashMessageService::class)->setMessage(_t('USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER') . ' !');
                }
                $this->referrer = filter_var($get['from'] ?? '', FILTER_SANITIZE_URL);
            } elseif (!empty($this->wantedEmail)) {
                $this->adminIsActing = true;

                $user = $this->userManager->getOneByEmail($this->wantedEmail); // In this case we need to load the right user

                if (empty($user)) { // Did not find the user in DB
                    $this->getService(FlashMessageService::class)->setMessage(_t('USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER') . ' !');
                }
            }
        } else {
            $userFromSession = $this->authenticationService->getLoggedUser();
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
            case 'resetpass':
                $this->resetPassword($user, $post);
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
            return $this->render('@core/usersettings.twig', [
                'adminIsActing' => $this->adminIsActing,
                'errorPasswordChange' => $this->errorPasswordChange,
                'errorUpdate' => $this->errorUpdate,
                'inIframe' => testUrlInIframe() == 'iframe',
                'referrer' => $this->referrer,
                'user' => $user,
                'userLoggedIn' => $this->userLoggedIn,
            ]);
        }
        $captcha = $this->captchaController->renderCaptchaField();
        $captcha = preg_replace('/(' .
            preg_quote('<div class="media-body">', '/') .
            "\s*" .
            preg_quote('<strong>', '/') .
            ')[^<]*(' .
            preg_quote('</strong>', '/') .
            ')/', '$1' . _t('USERSETTINGS_CAPTCHA_USER_CREATION') . '$2', $captcha);

        return $this->render('@core/user-signup-form.twig', [
            'error' => $this->error,
            'name' => $this->wantedUserName,
            'email' => $this->wantedEmail,
            'captcha' => $captcha,
            'regexUserName' => UserOperationsService::PATTERN_USER_NAME,
        ]);
    }

    private function logout()
    {
        // User wants to log out
        $this->authenticationService->logout();
        $this->getService(FlashMessageService::class)->setMessage(_t('USER_YOU_ARE_NOW_DISCONNECTED') . ' !');
        $this->wiki->Redirect($this->getService(UrlFormatter::class)->href());
    }

    private function deleteByAdmin(?User &$user = null)
    {
        if ($this->adminIsActing && !empty($this->wantedUserName)) {
            // Admin trying to delete user
            try {
                $this->csrfTokenChecker->checkToken('main', 'POST', 'csrf-token-delete', false);
                if (empty($user)) {
                    $this->errorUpdate = _t('USERSETTINGS_USER_NOT_DELETED') . ' user not found';

                    return null;
                }
                $this->userOperationsService->delete($user);
                $user = null;
                // forward
                $this->getService(FlashMessageService::class)->setMessage(_t('USER_DELETED') . ' !');
                $this->wiki->Redirect($this->getService(UrlFormatter::class)->href('', $this->referrer));
            } catch (TokenNotFoundException $th) {
                $this->errorUpdate = _t('USERSETTINGS_USER_NOT_DELETED') . ' ' . $th->getMessage();
            }
        }
    }

    private function update(array $post, User $user)
    {
        if ($this->adminIsActing || $this->userLoggedIn) {
            try {
                $this->csrfTokenChecker->checkToken('main', 'POST', 'csrf-token-update', false);

                $sanitizedPost = array_map(function ($item) {
                    return is_scalar($item) ? $item : '';
                }, $post);

                $this->userOperationsService->update(
                    $user,
                    $sanitizedPost
                );

                $user = $this->userManager->getOneByEmail($sanitizedPost['email']);

                if (!empty($user)) {
                    if ($this->userLoggedIn) { // In case it's the user trying to update oneself, need to reset the cookies
                        $this->authenticationService->login($user);
                    }
                    // forward
                    $this->getService(FlashMessageService::class)->setMessage(_t('USER_PARAMETERS_SAVED') . ' !');
                    if ($this->userLoggedIn) { // In case it's the usther trying to update oneself
                        $this->wiki->Redirect($this->getService(UrlFormatter::class)->href());
                    } else { // That's the admin acting, we need to pass the user on
                        $this->wiki->Redirect($this->getService(UrlFormatter::class)->href('', '', 'user=' . $this->wantedUserName . '&from=' . $this->referrer, false));
                    }
                } else { // Unable to update
                    throw new \Exception('');
                }
            } catch (TokenNotFoundException $th) {
                $this->errorUpdate = _t('USERSETTINGS_EMAIL_NOT_CHANGED') . ' ' . $th->getMessage();
            } catch (UserEmailAlreadyUsedException $th) {
                $email = isset($post['email']) && is_string($post['email']) ? htmlspecialchars($post['email']) : '';
                $this->errorUpdate = _t('USERSETTINGS_EMAIL_NOT_CHANGED') . ' ' . str_replace('{email}', $email, _t('USERSETTINGS_EMAIL_ALREADY_USED'));
            } catch (\Exception $th) {
                // TODO use a specific exception
                $this->errorUpdate = _t('USERSETTINGS_EMAIL_NOT_CHANGED') . ' ' . $th->getMessage();
            }
        }
    }

    private function changePassword(?User $user, array $post)
    {
        if ($this->userLoggedIn) {
            // User wants to change password
            if (!$this->authenticationService->checkPassword($post['oldpass'], $user)) { // check password first
                $this->errorPasswordChange = _t('USER_WRONG_PASSWORD') . ' !';
            } else { // user properly typed his old password in
                // check token
                try {
                    $this->csrfTokenChecker->checkToken('main', 'POST', 'csrf-token-changepass', false);

                    $password = $post['password'];
                    $this->authenticationService->setPassword($user, $password);
                    $this->getService(FlashMessageService::class)->setMessage(_t('USER_PASSWORD_CHANGED') . ' !');
                    // reload $user
                    $user = $this->userManager->getOneByName($user['name']);
                    if (!empty($user)) {
                        $this->authenticationService->login($user);
                    }
                    $this->wiki->Redirect($this->getService(UrlFormatter::class)->href());
                } catch (TokenNotFoundException $th) {
                    $this->errorPasswordChange = _t('USERSETTINGS_PASSWORD_NOT_CHANGED') . ' ' . $th->getMessage();
                } catch (BadFormatPasswordException|\Throwable $ex) {
                    // Something when wrong when updating the user in DB
                    $this->errorPasswordChange = _t('USERSETTINGS_PASSWORD_NOT_CHANGED') . ' ' . $ex->getMessage();
                }
            }
        }
    }

    private function resetPassword(?User $user, array $post)
    {
        $link = $this->userManager->sendPasswordRecoveryEmail($user);
        if (!boolval($this->getService(RuntimeConfig::class)['contact_disable_email_for_password'])) {
            Flash::success(str_replace('{email}', $user['email'], _t('RECOVERY_MESSAGE_SENT')));
        }
        $resetText = _t('RECOVERY_LINK');
        Flash::success("<a href='$link' target='_blank'>$resetText</a>");
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
                    $this->authenticationService->checkPasswordValidateRequirements($password)
                    && $post['confpassword'] !== $password
                ) {
                    $this->error = _t('USER_PASSWORDS_NOT_IDENTICAL') . '.';
                } else { // Password is correct
                    $_POST['submit'] = InputFilter::EDIT_PAGE_SUBMIT_VALUE;
                    list($state, $error) = $this->captchaController->checkCaptchaBeforeSave();
                    if (!$state) {
                        $this->error = $error;
                    } else {
                        $user = $this->userOperationsService->create([
                            'changescount' => 100,
                            'doubleclickedit' => 'Y',
                            'email' => $post['email'] ?? '',
                            'name' => $post['name'] ?? '',
                            'password' => $password,
                            'revisioncount' => 20,
                            'show_comments' => 'N',
                        ]);
                        if (!empty($user)) {
                            $this->authenticationService->login($user);
                            $this->wiki->Redirect($this->getService(UrlFormatter::class)->href()); // forward
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
            } catch (\Exception $ex) {
                $this->error = $ex->getMessage();
            }
        }
    }

    private function checklogged(array $post)
    {
        $this->error = _t('USER_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED') . '.';
    }
}
