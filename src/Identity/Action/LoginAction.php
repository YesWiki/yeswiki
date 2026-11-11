<?php

namespace YesWiki\Identity\Action;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\AvatarService;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\Service\SignIn\SignInFlow;
use YesWiki\Identity\Service\SignIn\Type\LocalType;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\PasswordProvider;
use YesWiki\Identity\SignIn\Contract\RedirectProvider;
use YesWiki\Identity\SignIn\SignInOutcome;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\CurrentRequest;
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
                ->notOffered()
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
                $this->getService(UrlFormatter::class)->generateLink($arg['signupurl'] ?? $this->getService(RuntimeConfig::class)->getValue('signupUrl', 'user/signup'))
            ),

            'profileurl' => empty($arg['profileurl'])
                ? $this->getService(UrlFormatter::class)->href('', 'user')
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
                    (CurrentRequest::input($this->getRequest(), 'action') == 'logout')
                    ? preg_replace('/(&|\\\?)$/m', '', (string)preg_replace('/(&|\\\?)action=logout(&)?/', '$1', (string)$incomingurl))
                    : $incomingurl
                ),

            'lostpasswordurl' => !boolval($this->params->get('contact_disable_email_for_password')) ? (!empty($arg['lostpasswordurl']) ? $this->getService(UrlFormatter::class)->generateLink($arg['lostpasswordurl']) :
            $this->getService(UrlFormatter::class)->href('', 'user/lost-password')) : '',

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

        $action = CurrentRequest::input($this->getRequest(), 'action', '');
        $vContext = CurrentRequest::input($this->getRequest(), 'context', $this->getService(PageContext::class)->getTag());
        if ($vContext !== $this->arguments['context']) {
            $action = '';
        }
        switch ($action) {
            case 'logout':
                $this->logout();
                break;
            case 'login':
                if ($this->getRequest()->isMethod('POST')) {
                    $this->login();
                    break;
                }

                return $this->renderForm('');

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

        unset($queryParams['context'], $queryParams[RequestGuard::FIELD]);

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
        ] + ($connected ? [] : $this->providersToOffer()));

        return $output;
    }

    private function login(): void
    {
        $incomingurl = $this->inputFilter->filterInput(INPUT_POST, 'incomingurl', FILTER_SANITIZE_URL, false, 'string');
        if (empty($incomingurl)) {
            $incomingurl = $this->arguments['incomingurl'];
        }

        $post = $this->getRequest()->request;
        $provider = $post->get('provider');
        $outcome = $this->getService(SignInFlow::class)->password(
            is_string($provider) && $provider !== '' ? $provider : LocalType::NAME,
            strval($post->get('name') ?: $post->get('email', '')),
            (string)$this->inputFilter->filterInput(INPUT_POST, 'password', FILTER_UNSAFE_RAW, false, 'string'),
            (bool)$this->inputFilter->filterInput(INPUT_POST, 'remember', FILTER_VALIDATE_BOOL, false, 'bool')
        );

        if ($outcome->kind !== SignInOutcome::SIGNED_IN) {
            $this->getService(FlashMessageService::class)->setMessage($outcome->kind === SignInOutcome::FORM_ERROR ? $outcome->message : $outcome->messageForPerson());
            $this->getService(Redirector::class)->redirect($incomingurl);
        }

        $name = $this->authenticationService->signedInName();
        $this->getService(Redirector::class)->redirect(
            (($post->get('userpage') == 'user') || $this->arguments['userpage'] == 'user') && $this->pageManager->getOne($name)
                ? $this->getService(UrlFormatter::class)->href('', $name)
                : $this->arguments['loggedinurl']
        );
    }

    /**
     * Every enabled provider, as the sign-in form lists them: the ones that send the person away as buttons, the ones that take a password as the form's tabs.
     *
     * @return array{redirectProviders: list<array{id: string, label: string, url: string}>, passwordProviders: list<array{id: string, label: string, local: bool}>}
     */
    private function providersToOffer(): array
    {
        $urls = $this->getService(UrlFormatter::class);
        $redirect = [];
        $password = [];
        $registry = $this->getService(ProviderRegistry::class);
        // the name people read: an entry without a label of its own goes by its type's
        $labelOf = fn (IdentityProvider $provider): string => ($config = $registry->config($provider->id())) !== null ? $registry->labelOf($config) : $provider->label();
        foreach ($registry->enabled() as $provider) {
            if ($provider instanceof RedirectProvider) {
                $redirect[] = [
                    'id' => $provider->id(),
                    'label' => $labelOf($provider),
                    'url' => $urls->href('', 'user/sign-in/' . rawurlencode($provider->id()), [self::RETURN_PARAM => $this->arguments['loggedinurl']], false),
                ];
            } elseif ($provider instanceof PasswordProvider) {
                $password[] = ['id' => $provider->id(), 'label' => $labelOf($provider), 'local' => $provider->type() === LocalType::NAME];
            }
        }

        return ['redirectProviders' => $redirect, 'passwordProviders' => $password];
    }

    private function logout(): void
    {
        if ($this->getService(RequestGuard::class)->linkIsSigned($this->getRequest())) {
            $this->authenticationService->logout();
            $this->getService(FlashMessageService::class)->setMessage(_t('LOGIN_YOU_ARE_NOW_DISCONNECTED'));
        } else {
            $this->getService(FlashMessageService::class)->setMessage(_t('CSRF_TOKEN_FAIL_ERROR'));
        }
        $this->getService(Redirector::class)->redirect((string)preg_replace('/(&|\\\?)$/m', '', (string)preg_replace('/(&|\\\?)action=logout(&)?/', '$1', $this->arguments['loggedouturl'])));
    }
}
