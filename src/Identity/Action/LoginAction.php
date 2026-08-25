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
                        ->default(''),
                    Setting::page('loggedouturl')
                        ->label(_t('AB_advanced_action_login_loggedouturl_label'))
                        ->hint(_t('AB_advanced_action_login_loggedouturl_hint'))
                        ->default(''),
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
                        ->default(''),
                    Setting::text('class')
                        ->label(_t('AB_advanced_action_login_class_label'))
                        ->default(''),
                    Setting::text('btnclass')
                        ->label(_t('AB_advanced_action_login_btnclass_label'))
                        ->default(''),
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

    /** The query parameter carrying "come back here once I have signed in". */
    public const RETURN_PARAM = 'return';

    protected AuthenticationService $authenticationService;
    protected PageManager $pageManager;
    protected TemplateEngine $templateEngine;
    protected InputFilter $inputFilter;
    protected UserManager $userManager;

    public function formatArguments($arg)
    {
        $noSignupButton = (isset($arg['signupurl']) && $arg['signupurl'] === '0') || $this->getService(RuntimeConfig::class)->getValue('noSignupButton', false);

        $incomingurl = !empty($arg['incomingurl'])
            ? ($this->getService(UrlFormatter::class)->generateLink($arg['incomingurl']) ?? $this->getIncomingUrlFromRequest())
        : $this->getIncomingUrlFromRequest();
        $returnurl = $this->getReturnUrlFromRequest();
        $this->templateEngine = $this->getService(TemplateEngine::class);

        return [
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

            'accounturl' => $this->getAccountUrl($incomingurl),

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
            $this->getService(UrlFormatter::class)->href('', 'MotDePassePerdu')) : '',

            'class' => !empty($arg['class']) ? $arg['class'] : '',
            'btnclass' => !empty($arg['btnclass']) ? $arg['btnclass'] : '',
            'nobtn' => $this->formatBoolean($arg, false, 'nobtn'),

            'template' => (empty($arg['template'])
                || empty(basename($arg['template']))
                || !$this->templateEngine->hasTemplate('@core/' . basename($arg['template'])))
                ? 'account-button.twig'
                : basename($arg['template']),
        ];
    }

    public function run(): ?string
    {
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->pageManager = $this->getService(PageManager::class);
        $this->inputFilter = $this->getService(InputFilter::class);
        $this->userManager = $this->getService(UserManager::class);

        $action = $this->getRequest()->get('action', '');
        $vContext = $this->getRequest()->get('context', $this->getService(PageContext::class)->getTag());
        if ($vContext !== $this->arguments['context']) {
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
     * The `?return=` of the current request, once it has been established that it points back into this wiki.
     */
    private function getReturnUrlFromRequest(): ?string
    {
        $request = $this->getRequest();

        $candidate = $request->request->all()[self::RETURN_PARAM]
            ?? $request->query->all()[self::RETURN_PARAM]
            ?? null;

        return $this->getService(UrlFormatter::class)->isInternal($candidate) ? (string)$candidate : null;
    }

    /** The sign-in screen, carrying the page to come back to. */
    private function getAccountUrl(string $incomingurl): string
    {
        $urlFormatter = $this->getService(UrlFormatter::class);
        $accounturl = $urlFormatter->href('', 'user');

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

    private function login(): void
    {
        $incomingurl = $this->inputFilter->filterInput(INPUT_POST, 'incomingurl', FILTER_SANITIZE_URL, false, 'string');
        if (empty($incomingurl)) {
            $incomingurl = $this->arguments['incomingurl'];
        }

        $destination = $incomingurl;
        try {
            $post = $this->getRequest()->request;
            $emailFallback = $post->get('email', '');

            if (!empty($post->get('name'))) {
                $name = strval($post->get('name'));

                $user = $this->userManager->getOneByName($name);

                if (empty($user) && empty($emailFallback)) {
                    $emailFallback = $post->get('name');
                }
            }

            if (empty($user) && !empty($emailFallback)) {
                $email = strval($emailFallback);

                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $user = $this->userManager->getOneByEmail($email);
                }
            }

            if (empty($user)) {
                // Whatever they typed, not just the name field: signing in by email alone is
                // offered, and recording an empty target for it would make the busiest kind of
                // guessing the one the Journal says nothing about.
                $this->authenticationService->recordFailedLogin(
                    (string)($post->get('name') ?: $emailFallback),
                    'unknown-user'
                );

                throw new LoginException(_t('LOGIN_WRONG_USER'));
            }

            $password = $this->inputFilter->filterInput(INPUT_POST, 'password', FILTER_UNSAFE_RAW, false, 'string');
            if (!$this->authenticationService->checkPassword($password, $user)) {
                if ($this->authenticationService->requiresPasswordReset($user)) {
                    $this->authenticationService->recordFailedLogin((string)$user['name'], 'password-format-obsolete');

                    throw new LoginException(boolval($this->params->get('contact_disable_email_for_password')) ? _t('LOGIN_PASSWORD_FORMAT_OBSOLETE_ASK_ADMIN') : _t('LOGIN_PASSWORD_FORMAT_OBSOLETE'));
                }

                $this->authenticationService->recordFailedLogin((string)$user['name'], 'wrong-password');

                throw new LoginException(_t('LOGIN_WRONG_PASSWORD'));
            }
            $remember = $this->inputFilter->filterInput(INPUT_POST, 'remember', FILTER_VALIDATE_BOOL, false, 'bool');
            $this->authenticationService->login($user, $remember);

            $destination = (($post->get('userpage') == 'user') || $this->arguments['userpage'] == 'user')
                && $this->pageManager->getOne($user['name'])
                ? $this->getService(UrlFormatter::class)->href('', $user['name'])
                : $this->arguments['loggedinurl'];
        } catch (LoginException $ex) {
            $this->getService(FlashMessageService::class)->setMessage($ex->getMessage());
            $this->getService(Redirector::class)->redirect($incomingurl);
        } catch (\Exception $ex) {
            Flash::error($ex->getMessage());
            $this->getService(Redirector::class)->redirect($incomingurl);
        }

        $this->getService(Redirector::class)->redirect($destination);
    }

    private function logout(): void
    {
        $this->authenticationService->logout();
        $this->getService(FlashMessageService::class)->setMessage(_t('LOGIN_YOU_ARE_NOW_DISCONNECTED'));
        $this->getService(Redirector::class)->redirect((string)preg_replace('/(&|\\\?)$/m', '', (string)preg_replace('/(&|\\\?)action=logout(&)?/', '$1', $this->arguments['loggedouturl'])));
    }
}
