<?php

namespace YesWiki\Identity\Action;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Exception\LoginException;
use YesWiki\Content\Service\PageManager;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class LoginAction extends YesWikiAction implements RegisteredAction
{
    /** `{{login}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'login';
    }

    protected $authenticationService;
    protected $pageManager;
    protected $templateEngine;
    protected $inputFilter;
    protected $userManager;

    public function formatArguments($arg)
    {
        $noSignupButton = (isset($arg['signupurl']) && $arg['signupurl'] === '0') || $this->wiki->GetConfigValue('noSignupButton', false);
        $incomingurl = !empty($arg['incomingurl'])
            ? $this->wiki->generateLink($arg['incomingurl'])
        : $this->getIncomingUrlFromRequest();
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
                    ($this->getRequest()->get('action') == 'logout')
                    ? preg_replace('/(&|\\\?)$/m', '', preg_replace('/(&|\\\?)action=logout(&)?/', '$1', $incomingurl))
                    : $incomingurl
                ),

            'lostpasswordurl' => !boolval($this->params->get('contact_disable_email_for_password')) ? (!empty($arg['lostpasswordurl']) ? $this->wiki->generateLink($arg['lostpasswordurl']) :
            // TODO : check page name for other languages
            $this->wiki->Href('', 'MotDePassePerdu')) : '',

            'class' => !empty($arg['class']) ? $arg['class'] : '',
            'btnclass' => !empty($arg['btnclass']) ? $arg['btnclass'] : '',
            'nobtn' => $this->formatBoolean($arg, false, 'nobtn'),
            'template' => (empty($arg['template'])
                || empty(basename($arg['template']))
                || !$this->templateEngine->hasTemplate('@core/' . basename($arg['template'])))
                ? 'default.twig'
                : basename($arg['template']),
        ];
    }

    public function run()
    {
        // get services
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->pageManager = $this->getService(PageManager::class);
        $this->inputFilter = $this->getService(InputFilter::class);
        $this->userManager = $this->getService(UserManager::class);

        $action = $this->getRequest()->get('action', '');
        $vContext = $this->getRequest()->get('context', $this->wiki->tag);
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

    private function normalizePathSegment(string $input): string
    {
        $ampersandPos = strpos($input, '&');

        if ($ampersandPos === false) {
            return rawurldecode($input);
        }

        $pathPart = substr($input, 0, $ampersandPos);
        $queryPart = substr($input, $ampersandPos);
        $decodedPath = rawurldecode(rtrim($pathPart, '='));

        return $decodedPath . $queryPart;
    }

    private function getIncomingUrlFromRequest(): string
    {
        $request = $this->getRequest();

        $urlParts = parse_url($request->getRequestUri());
        $queryParams = [];

        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
        }

        unset($queryParams['context']);

        $newQuery = http_build_query($queryParams);
        $clean = ($urlParts['path'] ?? '') . ($newQuery !== '' ? '?' . $newQuery : '');
        $clean = $this->normalizePathSegment(rtrim($clean, '='));

        return $request->getScheme() . '://' . $request->getHttpHost() . $clean;
    }

    private function renderForm(string $action): string
    {
        $user = $this->authenticationService->getLoggedUser();
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

        $output = $this->render("@core/{$this->arguments['template']}", [
            'connected' => $connected,
            'user' => $user['name'] ?? $this->getRequest()->request->get('name', ''),
            'email' => $user['email'] ?? $this->getRequest()->request->get('email', ''),
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
        $incomingurl = $this->inputFilter->filterInput(INPUT_POST, 'incomingurl', FILTER_SANITIZE_URL, false, 'string');
        if (empty($incomingurl)) {
            $incomingurl = $this->arguments['incomingurl'];
        }
        try {
            $post = $this->getRequest()->request;
            $emailFallback = $post->get('email', '');
            // First, try to find a user by name
            if (!empty($post->get('name'))) {
                // No need to filter the name, it will be escaped in the request to the database.
                // It can be possible to filter the name with the regex PATTERN_USER_NAME in UserManager, but if this regex changes,
                // existing users will be unable to login. So just let the database check if the user is here.
                $name = $post->get('name');

                $user = $this->userManager->getOneByName($name);

                // TODO Strange, but the code allow an email to be pass in $_POST['name'] instead of $_POST['email']
                // So if we don't find the user by name, it can be because it is an email instead of a username
                if (empty($user) && empty($emailFallback)) {
                    $emailFallback = $post->get('name');
                }
            }

            // Then, try to find a user by email
            if (empty($user) && !empty($emailFallback)) {
                // No need to filter the email, it will be escaped in the request to the database.
                $email = $emailFallback;

                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $user = $this->userManager->getOneByEmail($email);
                }
            }

            // Stop here if we don't have a user
            if (empty($user)) {
                throw new LoginException(_t('LOGIN_WRONG_USER'));
            }

            $password = $this->inputFilter->filterInput(INPUT_POST, 'password', FILTER_UNSAFE_RAW, false, 'string');
            if (!$this->authenticationService->checkPassword($password, $user)) {
                throw new LoginException(_t('LOGIN_WRONG_PASSWORD'));
            }
            $remember = $this->inputFilter->filterInput(INPUT_POST, 'remember', FILTER_VALIDATE_BOOL, false, 'bool');
            $this->authenticationService->login($user, $remember);

            // si l'on veut utiliser la page d'accueil correspondant au nom d'utilisateur
            if ((($post->get('userpage') == 'user') || $this->arguments['userpage'] == 'user') && $this->pageManager->getOne($user['name'])) {
                $this->wiki->Redirect($this->wiki->Href('', $user['name']));
            } else {
                $this->wiki->Redirect($this->arguments['loggedinurl']);
            }
        } catch (LoginException $ex) {
            // on affiche une erreur sur le NomWiki sinon
            $this->wiki->SetMessage($ex->getMessage());
            $this->wiki->Redirect($incomingurl);
        } catch (\Exception $ex) {
            // catches AuthenticationService::login()'s BadLoginException (ticket 07's activation
            // gate, already carrying a full user-facing message) along with anything else
            Flash::error($ex->getMessage());
            $this->wiki->Redirect($incomingurl);
        }
    }

    private function logout()
    {
        $this->authenticationService->logout();
        $this->wiki->SetMessage(_t('LOGIN_YOU_ARE_NOW_DISCONNECTED'));
        $this->wiki->Redirect(preg_replace('/(&|\\\?)$/m', '', preg_replace('/(&|\\\?)action=logout(&)?/', '$1', $this->arguments['loggedouturl'])));
        $this->wiki->exit();
    }
}
