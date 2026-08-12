<?php

namespace YesWiki\Identity\Action;

use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Exception\LoginException;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\AvatarService;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;

class LoginAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{login}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'login';
    }

    public function components(): array
    {
        return [
            Component::for('login')
                ->category(Category::Forms)
                ->label(_t('AB_advanced_action_login_label'))
                ->icon('login')
                ->previewHeight('200px')
                ->settings(
                    Setting::choice('template', [
                        'default.twig' => _t('AB_advanced_action_login_template_default'),
                        'account-link.twig' => _t('AB_advanced_action_login_template_account_link'),
                        'horizontal.twig' => _t('AB_advanced_action_login_template_horizontal'),
                        'dropdown.twig' => _t('AB_advanced_action_login_template_dropdown'),
                    ])
                        ->label(_t('AB_advanced_action_login_template_label'))
                        ->default('default.twig'),
                    Setting::page('signupurl')
                        ->label(_t('AB_advanced_action_login_signupurl_label'))
                        ->hint(_t('AB_advanced_action_login_signupurl_hint'))
                        ->default(''),
                    Setting::page('incomingurl')
                        ->label(_t('AB_advanced_action_login_incomingurl_label'))
                        ->default(''),
                    Setting::page('loggedinurl')
                        ->label(_t('AB_advanced_action_login_loggedinurl_label'))
                        ->hint(_t('AB_advanced_action_login_loggedinurl_hint'))
                        ->default('')
                        ->advanced(),
                    Setting::page('loggedouturl')
                        ->label(_t('AB_advanced_action_login_loggedouturl_label'))
                        ->hint(_t('AB_advanced_action_login_loggedouturl_hint'))
                        ->default('')
                        ->advanced(),
                    Setting::checkbox('userpage')
                        ->label(_t('AB_advanced_action_login_userpage_label'))
                        ->default('')
                        ->checkedValues('user', ''),
                    Setting::page('lostpasswordurl')
                        ->label(_t('AB_advanced_action_login_lostpasswordurl_label'))
                        ->hint(_t('AB_advanced_action_login_lostpasswordurl_hint'))
                        ->default(''),
                    Setting::page('profileurl')
                        ->label(_t('AB_advanced_action_login_profileurl_label'))
                        ->hint(_t('AB_advanced_action_login_profileurl_hint'))
                        ->default('')
                        ->advanced(),
                    Setting::text('class')
                        ->label(_t('AB_advanced_action_login_class_label'))
                        ->default('')
                        ->advanced(),
                    Setting::text('btnclass')
                        ->label(_t('AB_advanced_action_login_btnclass_label'))
                        ->default('')
                        ->advanced(),
                    Setting::checkbox('nobtn')
                        ->label(_t('AB_advanced_action_login_nobtn_label'))
                        ->default('false')
                        ->showIf([
                            'template' => 'account-link\\.(?:twig|tpl\\.html)',
                        ])
                        ->checkedValues('true', 'false'),
                ),
        ];
    }

    /**
     * The query parameter carrying "come back here once I have signed in".
     *
     * The account button puts the page it was clicked from in it, and the sign-in that
     * follows reads it back -- the two are on different pages (the button is in the
     * navbar, the form is on /user), so the URL is the only thing that travels between
     * them. Never trusted as given: see UrlFormatter::isInternal().
     */
    public const RETURN_PARAM = 'return';

    protected $authenticationService;
    protected $pageManager;
    protected $templateEngine;
    protected $inputFilter;
    protected $userManager;

    public function formatArguments($arg)
    {
        $noSignupButton = (isset($arg['signupurl']) && $arg['signupurl'] === '0') || $this->getService(RuntimeConfig::class)->getValue('noSignupButton', false);
        // `?? $this->getIncomingUrlFromRequest()`: a supplied `incomingurl=` that does not
        // resolve to a link is no address at all, and everything downstream of this --
        // where the form posts, where signing in ends -- needs one
        $incomingurl = !empty($arg['incomingurl'])
            ? ($this->getService(UrlFormatter::class)->generateLink($arg['incomingurl']) ?? $this->getIncomingUrlFromRequest())
        : $this->getIncomingUrlFromRequest();
        $returnurl = $this->getReturnUrlFromRequest();
        $this->templateEngine = $this->getService(TemplateEngine::class);

        return [
            // as there can be multiple login actions in one page, we can add a context so that the good action is used
            // we also add a default value with the pageTag if no context provided, assuming there will never be 2 times the login action in the same page.
            'context' => $arg['context'] ?? $this->getService(PageContext::class)->getTag(),
            'signupurl' => $noSignupButton ? '0' : (
                $this->getService(UrlFormatter::class)->generateLink($arg['signupurl'] ?? $this->getService(RuntimeConfig::class)->getValue('signupUrl', 'ParametresUtilisateur'))
            ),

            'profileurl' => empty($arg['profileurl'])
                ? $this->getService(UrlFormatter::class)->href('', 'ParametresUtilisateur')
                : (
                    $arg['profileurl'] == 'WikiName'
                    ? 'WikiName'
                    : $this->getService(UrlFormatter::class)->generateLink($arg['profileurl'])
                ),

            'incomingurl' => $incomingurl,

            // where the account button sends someone who is not signed in: the sign-in
            // screen, carrying the page they were on so it can send them back
            'accounturl' => $this->getAccountUrl($incomingurl),

            // `?return=` outranks "back to where the form was submitted", which on the
            // sign-in screen is the sign-in screen. An explicit `loggedinurl=` still wins
            // over both: a page that says where sign-in leads means it.
            'loggedinurl' => empty($arg['loggedinurl'])
                ? ($returnurl ?? $incomingurl)
                : $this->getService(UrlFormatter::class)->generateLink($arg['loggedinurl']),

            'loggedouturl' => empty($arg['loggedouturl'])
                ? $incomingurl
                : $this->getService(UrlFormatter::class)->generateLink($arg['loggedouturl']),

            'userpage' => !empty($arg['userpage'])
                ? (
                    $arg['userpage'] == 'user'
                    ? 'user'
                    : $this->getService(UrlFormatter::class)->generateLink($arg['userpage'])
                )
                : (
                    ($this->getRequest()->get('action') == 'logout')
                    ? preg_replace('/(&|\\\?)$/m', '', (string)preg_replace('/(&|\\\?)action=logout(&)?/', '$1', (string)$incomingurl))
                    : $incomingurl
                ),

            'lostpasswordurl' => !boolval($this->params->get('contact_disable_email_for_password')) ? (!empty($arg['lostpasswordurl']) ? $this->getService(UrlFormatter::class)->generateLink($arg['lostpasswordurl']) :
            // TODO : check page name for other languages
            $this->getService(UrlFormatter::class)->href('', 'MotDePassePerdu')) : '',

            'class' => !empty($arg['class']) ? $arg['class'] : '',
            'btnclass' => !empty($arg['btnclass']) ? $arg['btnclass'] : '',
            'nobtn' => $this->formatBoolean($arg, false, 'nobtn'),
            // The default is the account button -- an icon, or your face. A caller that
            // wants the fields asks for `login-form.twig` by name.
            //
            // There is deliberately no template called `default`: the name meant the form
            // until this release, and a page still naming it would have silently become a
            // button. It resolves to nothing, so it lands here instead, and the migration
            // that renames it says so out loud.
            'template' => (empty($arg['template'])
                || empty(basename($arg['template']))
                || !$this->templateEngine->hasTemplate('@core/' . basename($arg['template'])))
                ? 'account-button.twig'
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
        $vContext = $this->getRequest()->get('context', $this->getService(PageContext::class)->getTag());
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

    /**
     * The `?return=` of the current request, once it has been established that it points
     * back into this wiki. Anything else -- another site, a `javascript:` URL, nothing at
     * all -- answers null, and the caller falls back to its own default.
     */
    private function getReturnUrlFromRequest(): ?string
    {
        $request = $this->getRequest();
        // ->all() rather than ->get(): the parameter is whatever a URL carried, and
        // `?return[]=x` makes Symfony's get() throw on a value it was promised was scalar
        $candidate = $request->request->all()[self::RETURN_PARAM]
            ?? $request->query->all()[self::RETURN_PARAM]
            ?? null;

        return $this->getService(UrlFormatter::class)->isInternal($candidate) ? (string)$candidate : null;
    }

    /**
     * The sign-in screen, carrying the page to come back to.
     *
     * Not added when the visitor is already on an account screen: `/user?return=/user` is
     * a round trip to where they are, and it would be the first thing they see in the
     * address bar of the page they came to sign in on.
     */
    private function getAccountUrl(string $incomingurl): string
    {
        $urlFormatter = $this->getService(UrlFormatter::class);
        $accounturl = $urlFormatter->href('', 'user');

        // an account screen is `…/user`, `…/user/pages`, `…/user&x=1` -- and NOT a page
        // whose tag merely starts with those four letters
        $onAccountScreen = $incomingurl === $accounturl
            || preg_match('#^' . preg_quote($accounturl, '#') . '[/&?]#', $incomingurl) === 1;

        return $onAccountScreen
            ? $accounturl
            : $urlFormatter->href('', 'user', [self::RETURN_PARAM => $incomingurl], false);
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
                $pageMenuUserContent = $this->getService(MarkdownFormatterService::class)->format('{{include page="PageMenuUser"}}');
            }
            if ($this->arguments['profileurl'] == 'WikiName') {
                $this->arguments['profileurl'] = $this->getService(UrlFormatter::class)->href('edit', $user['name']);
            }
        } elseif ($action == 'checklogged') {
            $error = _t('LOGIN_COOKIES_ERROR');
        }

        $userName = $user['name'] ?? $this->getRequest()->request->get('name', '');

        $output = $this->render("@core/{$this->arguments['template']}", [
            'connected' => $connected,
            'user' => $userName,
            // only for a session there is: an avatar for a name typed into a form that has
            // not been submitted yet would draw a stranger's face on the wiki
            'avatar' => $connected ? $this->getService(AvatarService::class)->forName((string)$userName) : null,
            'email' => $user['email'] ?? $this->getRequest()->request->get('email', ''),
            'incomingurl' => $this->arguments['incomingurl'],
            'accounturl' => $this->arguments['accounturl'],
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
        // where a successful sign-in ends, settled inside the try and acted on after it
        $destination = $incomingurl;
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
                // An md5 stored by an older YesWiki no longer authenticates. Saying "wrong
                // password" to its owner is a dead end -- the password they typed may well be
                // the right one, and no number of retries will change the answer. The reset
                // flow is the only way through, so name it; and only point at the lost-password
                // link when this wiki actually offers it.
                if ($this->authenticationService->requiresPasswordReset($user)) {
                    throw new LoginException(boolval($this->params->get('contact_disable_email_for_password')) ? _t('LOGIN_PASSWORD_FORMAT_OBSOLETE_ASK_ADMIN') : _t('LOGIN_PASSWORD_FORMAT_OBSOLETE'));
                }

                throw new LoginException(_t('LOGIN_WRONG_PASSWORD'));
            }
            $remember = $this->inputFilter->filterInput(INPUT_POST, 'remember', FILTER_VALIDATE_BOOL, false, 'bool');
            $this->authenticationService->login($user, $remember);

            // si l'on veut utiliser la page d'accueil correspondant au nom d'utilisateur
            $destination = (($post->get('userpage') == 'user') || $this->arguments['userpage'] == 'user')
                && $this->pageManager->getOne($user['name'])
                ? $this->getService(UrlFormatter::class)->href('', $user['name'])
                : $this->arguments['loggedinurl'];
        } catch (LoginException $ex) {
            // on affiche une erreur sur le NomWiki sinon
            $this->getService(FlashMessageService::class)->setMessage($ex->getMessage());
            $this->getService(Redirector::class)->redirect($incomingurl);
        } catch (\Exception $ex) {
            // catches AuthenticationService::login()'s BadLoginException (ticket 07's activation
            // gate, already carrying a full user-facing message) along with anything else
            Flash::error($ex->getMessage());
            $this->getService(Redirector::class)->redirect($incomingurl);
        }

        // OUTSIDE the try: redirecting throws (Redirector), ExitException is an ordinary
        // \Exception, and the catch-all above was therefore catching the successful
        // sign-in's own redirect -- flashing an empty error and redirecting a second time,
        // to $incomingurl. Invisible while the two addresses were always the same one;
        // it is what swallowed the first `?return=` that differed from it.
        $this->getService(Redirector::class)->redirect($destination);
    }

    private function logout()
    {
        $this->authenticationService->logout();
        $this->getService(FlashMessageService::class)->setMessage(_t('LOGIN_YOU_ARE_NOW_DISCONNECTED'));
        $this->getService(Redirector::class)->redirect((string)preg_replace('/(&|\\\?)$/m', '', (string)preg_replace('/(&|\\\?)action=logout(&)?/', '$1', $this->arguments['loggedouturl'])));
    }
}
