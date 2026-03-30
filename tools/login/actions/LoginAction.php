<?php

/**
 * $_REQUEST :
 * - action : login|logout|checklogged
 * $_SERVER :
 * - REQUEST_URI
 * $_POST :
 * - name
 * - email
 * - password
 * - remember
 * - userpage.
 */

namespace YesWiki\Login;

use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Login\Exception\LoginException;
use YesWiki\Security\Controller\SecurityController;

class LoginAction extends YesWikiAction
{
    protected $authController;
    protected $pageManager;
    protected $templateEngine;
    protected $securityController;
    protected $userManager;

    public function formatArguments($arg)
    {
        $noSignupButton = (isset($arg['signupurl']) && $arg['signupurl'] === '0') || $this->wiki->GetConfigValue('noSignupButton', false);
        $incomingurl = !empty($arg['incomingurl'])
            ? $this->wiki->generateLink($arg['incomingurl'])
            : $this->getIncomingUrlFromServer($_SERVER ?? []);
        $this->templateEngine = $this->getService(TemplateEngine::class);

        return [
            // as there can be multiple login actions in one page, we can add a context so that the good action is used
            // we also add a default value with the pageTag if no context provided, assuming there will never be 2 times the login action in the same page.
            'context' => $arg['context'] ?? $this->wiki->tag,
            'signupurl' => $noSignupButton ? '0' : (
                $this->wiki->generateLink($arg['signupurl'] ?? $this->wiki->GetConfigValue('signupUrl', 'ParametresUtilisateur'))
            ),

            'profileurl' => empty($arg['profileurl'])
                ? $this->wiki->Href('', 'ParametresUtilisateur')
                : (
                    $arg['profileurl'] == 'WikiName'
                    ? 'WikiName'
                    : $this->wiki->generateLink($arg['profileurl'])
                ),

            'incomingurl' => $incomingurl,

            'loggedinurl' => empty($arg['loggedinurl'])
                ? $incomingurl
                : $this->wiki->generateLink($arg['loggedinurl']),

            'loggedouturl' => empty($arg['loggedouturl'])
                ? $incomingurl
                : $this->wiki->generateLink($arg['loggedouturl']),

            'userpage' => !empty($arg['userpage'])
                ? (
                    $arg['userpage'] == 'user'
                    ? 'user'
                    : $this->wiki->generateLink($arg['userpage'])
                )
                : (
                    (isset($_REQUEST['action']) && $_REQUEST['action'] == 'logout')
                    ? preg_replace('/(&|\\\?)$/m', '', preg_replace('/(&|\\\?)action=logout(&)?/', '$1', $incomingurl))
                    : $incomingurl
                ),

            'lostpasswordurl' => !boolval($this->params->get('contact_disable_email_for_password')) ? (!empty($arg['lostpasswordurl']) ? $this->wiki->generateLink($arg['lostpasswordurl']) :
            // TODO : check page name for other languages
            $this->wiki->Href('', 'MotDePassePerdu')) : '',

            'class' => !empty($arg['class']) ? $arg['class'] : '',
            'btnclass' => !empty($arg['btnclass']) ? $arg['btnclass'] : '',
            'nobtn' => $this->formatBoolean($arg, false, 'nobtn'),
            'template' => (empty($arg['template']) ||
                empty(basename($arg['template'])) ||
                !$this->templateEngine->hasTemplate('@login/' . basename($arg['template'])))
                ? 'default.twig'
                : basename($arg['template']),
        ];
    }

    public function run()
    {
        // get services
        $this->authController = $this->getService(AuthController::class);
        $this->pageManager = $this->getService(PageManager::class);
        $this->securityController = $this->getService(SecurityController::class);
        $this->userManager = $this->getService(UserManager::class);

        $action = $_REQUEST['action'] ?? '';
        $vContext = $_REQUEST['context'] ?? $this->wiki->tag;
        if ($vContext !== $this->arguments['context']) {
            // no action if not in the good context
            $action = '';
        }
        switch ($action) {
            case 'logout':
                $this->logout();
                break;
            case 'login':
                $this->login();
                break;

            case 'checklogged':
            default:
                return $this->renderForm($action);
        }

        return null;
    }

    private function getIncomingUrlFromServer(array $server): string
    {
        $parts = parse_url($server['REQUEST_URI']);
        $params = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $parsedQuery);

            foreach ($parsedQuery as $key => $value) {
                // Skip 'context' parameter
                if ($key === 'context') {
                    continue;
                }

                if (is_array($value)) {
                    foreach ($value as $val) {
                        $params[] = ['key' => $key, 'value' => $val];
                    }
                } else {
                    $params[] = ['key' => $key, 'value' => $value];
                }
            }
        }

        $newQuery = [];
        foreach ($params as $param) {
            $key = urlencode($param['key']);
            $value = $param['value'];

            if (empty($value)) {
                $newQuery[] = $key;
            } else {
                $newQuery[] = $key . '=' . urlencode($value);
            }
        }

        $queryString = implode('&', $newQuery);

        $separator = '';
        if (!empty($queryString)) {
            $separator = ($this->wiki->config['rewrite_mode'] ? '?' : '&');
        }

        return $this->wiki->getBaseUrl() . ($parts['path'] ?? '') . $separator . $queryString;
    }

    private function renderForm(string $action): string
    {
        $user = $this->authController->getLoggedUser();
        $connected = !empty($user);
        $error = '';
        $pageMenuUserContent = '';
        if ($connected) {
            $pageMenuUser = $this->pageManager->getOne('PageMenuUser');
            if (!empty($pageMenuUser)) {
                $pageMenuUserContent = $this->wiki->Format('{{include page="PageMenuUser"}}');
            }
            if ($this->arguments['profileurl'] == 'WikiName') {
                $this->arguments['profileurl'] = $this->wiki->Href('edit', $user['name']);
            }
        } elseif ($action == 'checklogged') {
            $error = _t('LOGIN_COOKIES_ERROR');
        }

        $output = $this->render("@login/{$this->arguments['template']}", [
            'connected' => $connected,
            'user' => ((isset($user['name'])) ? $user['name'] : ((isset($_POST['name'])) ? $_POST['name'] : '')),
            'email' => ((isset($user['email'])) ? $user['email'] : ((isset($_POST['email'])) ? $_POST['email'] : '')),
            'incomingurl' => $this->arguments['incomingurl'],
            'signupurl' => $this->arguments['signupurl'],
            'lostpasswordurl' => !boolval($this->params->get('contact_disable_email_for_password')) ? $this->arguments['lostpasswordurl'] : '',
            'profileurl' => $this->arguments['profileurl'],
            'userpage' => $this->arguments['userpage'],
            'PageMenuUser' => $pageMenuUserContent,
            'btnclass' => $this->arguments['btnclass'],
            'class' => $this->arguments['class'],
            'nobtn' => $this->arguments['nobtn'],
            'error' => $error,
            'context' => $this->arguments['context'],
        ]);

        return $output;
    }

    private function login()
    {
        $incomingurl = $this->securityController->filterInput(INPUT_POST, 'incomingurl', FILTER_SANITIZE_URL, false, 'string');
        if (empty($incomingurl)) {
            $incomingurl = $this->arguments['incomingurl'];
        }
        try {
            // First, try to find a user by name
            if (!empty($_POST['name'])) {
                // No need to filter the name, it will be escaped in the request to the database.
                // It can be possible to filter the name with the regex PATTERN_USER_NAME in UserManager, but if this regex changes,
                // existing users will be unable to login. So just let the database check if the user is here.
                $name = $_POST['name'];

                $user = $this->userManager->getOneByName($name);

                // TODO Strange, but the code allow an email to be pass in $_POST['name'] instead of $_POST['email']
                // So if we don't find the user by name, it can be because it is an email instead of a username
                if (empty($user) && empty($_POST['email'])) {
                    $_POST['email'] = $_POST['name'];
                }
            }

            // Then, try to find a user by email
            if (empty($user) && !empty($_POST['email'])) {
                // No need to filter the email, it will be escaped in the request to the database.
                $email = $_POST['email'];

                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $user = $this->userManager->getOneByEmail($email);
                }
            }

            // Stop here if we don't have a user
            if (empty($user)) {
                throw new LoginException(_t('LOGIN_WRONG_USER'));
            }

            $password = $this->securityController->filterInput(INPUT_POST, 'password', FILTER_UNSAFE_RAW, false, 'string');
            if (!$this->authController->checkPassword($password, $user)) {
                throw new LoginException(_t('LOGIN_WRONG_PASSWORD'));
            }
            $remember = $this->securityController->filterInput(INPUT_POST, 'remember', FILTER_VALIDATE_BOOL, false, 'bool');
            $this->authController->login($user, $remember);

            // si l'on veut utiliser la page d'accueil correspondant au nom d'utilisateur
            if (((!empty($_POST['userpage']) && $_POST['userpage'] == 'user') || $this->arguments['userpage'] == 'user') && $this->pageManager->getOne($user['name'])) {
                $this->wiki->Redirect($this->wiki->Href('', $user['name']));
            } else {
                $this->wiki->Redirect($this->arguments['loggedinurl']);
            }
        } catch (LoginException $ex) {
            // on affiche une erreur sur le NomWiki sinon
            $this->wiki->SetMessage($ex->getMessage());
            $this->wiki->Redirect($incomingurl);
        } catch (Exception $ex) {
            // error error
            Flash::error($ex->getMessage());
            $this->wiki->Redirect($incomingurl);
        }
    }

    private function logout()
    {
        $this->authController->logout();
        $this->wiki->SetMessage(_t('LOGIN_YOU_ARE_NOW_DISCONNECTED'));
        $this->wiki->Redirect(preg_replace('/(&|\\\?)$/m', '', preg_replace('/(&|\\\?)action=logout(&)?/', '$1', $this->arguments['loggedouturl'])));
        $this->wiki->exit();
    }
}
