<?php

namespace YesWiki\Identity\Service;

use DateTime;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Kernel\Entity\CookieData;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Exception\BadFormatPasswordException;
use YesWiki\Identity\Exception\BadLoginException;
use YesWiki\Identity\Exception\BadUserConnectException;
use YesWiki\Core\YesWikiController;
use YesWiki\Wiki;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Identity\Service\PasswordHasherFactory;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;

// this trait should be into src/traits/LimitationsTrait folder
// with namespace namespace YesWiki\Core\Trait; but it is not working for old php version previous php 8

trait LimitationsTrait
{
    /**
     * init and store limitations in limitations array.
     */
    private function initLimitationHelper(string $parameterName, string $limitationKey, $type, $default, string $errorMessageKey)
    {
        $this->limitations[$limitationKey] = $default;
        if ($this->params->has($parameterName)) {
            $parameter = $this->params->get($parameterName);
            if (!filter_var($parameter, FILTER_VALIDATE_INT)) {
                trigger_error(_t($errorMessageKey));
            } else {
                $this->limitations[$limitationKey] = $parameter;
            }
        }
    }
}

class AuthenticationService extends YesWikiController
{
    use LimitationsTrait;

    public const DEFAULT_PASSWORD_MINIMUM_LENGTH = 5;
    protected const DATE_LENGTH_IN_TOKEN = 17;
    protected const DATE_FORMAT_IN_TOKEN = 'Ymd-H-i-s';

    private $limitations;
    protected $accountActivationService;
    protected $params;
    protected $passwordHasherFactory;
    protected $hibernationService;
    protected $userManager;
    private $loggedUserCache;

    public function __construct(
        AccountActivationService $accountActivationService,
        HibernationService $hibernationService,
        ParameterBagInterface $params,
        PasswordHasherFactory $passwordHasherFactory,
        UserManager $userManager,
        Wiki $wiki
    ) {
        $this->accountActivationService = $accountActivationService;
        $this->hibernationService = $hibernationService;
        $this->params = $params;
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->userManager = $userManager;
        $this->wiki = $wiki;
        $this->initLimitations();
    }

    /** Initializes object limitation properties using values from the config file.
     *
     * @return void
     */
    private function initLimitations()
    {
        $this->limitations = [];
        $this->initLimitationHelper(
            'user_password_min_length',
            'passwordMinimumLength',
            FILTER_VALIDATE_INT,
            self::DEFAULT_PASSWORD_MINIMUM_LENGTH,
            'USER_PASSWORD_MIN_LENGTH_NOT_INT'
        );
    }

    /** checks if the given string is the user's password.
     *
     * @return bool True if OK or false if any problems
     */
    public function checkPassword(string $plainTextPassword, User $user)
    {
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);
        $hashedPassword = $user->getPassword();
        if (!$passwordHasher->verify($hashedPassword, $plainTextPassword)) {
            return false;
        }
        if ($passwordHasher->needsRehash($hashedPassword) && !$this->hibernationService->isWikiHibernated()) {
            $newHashedPassword = $passwordHasher->hash($plainTextPassword);
            $this->userManager->upgradePassword($user, $newHashedPassword);
        }

        return true;
    }

    /**
     * force a new password when renewing password.
     *
     * @throws BadFormatPasswordException
     */
    public function setPassword(User $user, string $plainTextPassword)
    {
        $this->checkPasswordValidateRequirements($plainTextPassword);
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);
        $newHashedPassword = $passwordHasher->hash($plainTextPassword);
        $this->userManager->upgradePassword($user, $newHashedPassword);
    }

    /**
     * check if password respets the requirements.
     *
     * @throws BadFormatPasswordException
     */
    public function checkPasswordValidateRequirements(string $password): bool
    {
        if (strlen($password) < $this->limitations['passwordMinimumLength']) {
            throw new BadFormatPasswordException(_t('USER_PASSWORD_TOO_SHORT') . '. ' . _t('USER_PASSWORD_MINIMUM_NUMBER_OF_CHARACTERS_IS') . ' ' . $this->limitations['passwordMinimumLength'] . '.');
        }

        return true;
    }

    /**
     * connect a user from SESSION or COOKIES.
     */
    public function connectUser()
    {
        $this->cleanOldFormatCookie();
        try {
            try {
                // faster to connect from session
                $data = $this->connectUserFromSession();
                if ($this->getExpirationTimeStamp($data['lastConnectionDate'], $data['remember']) < time()) {
                    throw new BadUserConnectException('Not connected via session');
                }
            } catch (BadUserConnectException $th) {
                // otherwise use cookies
                $data = $this->connectUserFromCookies();
                if ($this->getExpirationTimeStamp($data['lastConnectionDate'], $data['remember']) < time()) {
                    $this->logout();
                }
            }

            // connect in SESSION
            $this->login($data['user'], $data['remember'] ? 1 : 0);
        } catch (BadUserConnectException $th) {
            if (
                empty($_SESSION['user']['name'])
                || empty($data['user']['name'])
                || $data['user']['name'] != $_SESSION['user']['name']
                || !$this->wiki->UserIsAdmin($data['user']['name'])
            ) {
                // do not disconnect admin during update
                $this->logout();
            }
        } catch (BadLoginException $th) {
            // login()'s activation gate rejected this session/cookie's own user (e.g. an
            // admin inactivated the account after it was already logged in) -- this is
            // connectUser()'s routine per-request re-hydration, not a login form
            // submission, so there's no request context to surface the message to beyond
            // a flash on the next render
            Flash::error($th->getMessage());
            $this->logout();
        }
    }

    // methods imported from UserManager

    public function getLoggedUser()
    {
        // If the user isn't logged
        if (!isset($_SESSION['user']) || empty($_SESSION['user']['name'])) {
            return '';
        }

        // Else, if user is logged, store the user in a cache
        if ($this->loggedUserCache === null || (is_array($this->loggedUserCache) && $this->loggedUserCache['name'] !== $_SESSION['user']['name'])) {
            $user = $this->userManager->getOneByName($_SESSION['user']['name']);
            if (!empty($user)) {
                $this->loggedUserCache = $user->getArrayCopy();
            } else {
                $this->loggedUserCache = '';
            }
        }

        return $this->loggedUserCache;
    }

    public function getLoggedUserName()
    {
        if ($user = $this->getLoggedUser()) {
            $name = $user['name'];
        } else {
            $name = $this->wiki->isCli() ? '' : $this->getRequest()->getClientIp();
        }

        return $name;
    }

    public function getExpirationTimeStamp(\DateTime $startTime, bool $remember): int
    {
        // 90 days if remember otherwise 1 hour
        return $startTime->getTimestamp() + ($remember ? 90 * 24 * 60 * 60 : 60 * 60);
    }

    /**
     * @throws BadLoginException if signup_email_activation is on and this user isn't
     *                           activated yet (an activation email is (re-)sent as a
     *                           side effect)
     */
    public function login($user, $remember = 0)
    {
        $userName = empty($user['name']) ? null : $user['name'];
        if (
            $userName !== null
            // cheapest, most-likely-to-short-circuit check first: skip every other check
            // (including $this->wiki->UserIsAdmin(), which needs a fully-bootstrapped Wiki)
            // entirely when the feature is off, its default
            && in_array($this->params->get('signup_email_activation'), [1, true, '1', 'true'], true)
            && !$this->hasLoginExtensions()
            && !$this->wiki->UserIsAdmin($userName)
            && !$this->accountActivationService->isActivated($userName)
            && (empty($GLOBALS['utilisateur_wikini']) || $GLOBALS['utilisateur_wikini'] != $userName)
        ) {
            try {
                $this->accountActivationService->sendActivationLink($userName);
            } catch (\Throwable $th) {
                throw new BadLoginException(_t('ACCOUNTACTIVATION_BY_EMAIL_WARNING', ['message' => _t('ACCOUNTACTIVATION_BY_EMAIL_MESSAGE_NOT_SENT')]));
            }
            throw new BadLoginException(_t('ACCOUNTACTIVATION_BY_EMAIL_WARNING', ['message' => _t('ACCOUNTACTIVATION_BY_EMAIL_MESSAGE_SENT')]));
        }

        $previousUserName = $_SESSION['user']['name'] ?? null;
        if (isset($_SESSION['user']) && $_SESSION['user']['name'] != $user['name']) {
            $this->cleanSensitiveDataFromSession();
        }
        $remember = filter_var($remember, FILTER_VALIDATE_BOOL);

        $currentDateTime = new \DateTime();
        $_SESSION['user'] =
            empty($user['name'])
            ? []
            : [
                'name' => $user['name'],
                'lastConnection' => $currentDateTime->getTimestamp(),
            ];

        if (!empty($user['name']) && $user['name'] !== $previousUserName && !$this->wiki->isCli()) {
            // regenerate the session ID whenever the authenticated identity actually changes
            // (anonymous -> user, or user A -> user B) to prevent session fixation;
            // this deliberately excludes connectUser()'s routine per-request re-hydration of
            // an already logged-in user, which calls login() again with the same name each time
            session_regenerate_id(true);
        }

        if (!$this->wiki->isCli()) {
            if (!$user instanceof User) {
                if (!empty($user['name'])) {
                    $user = $this->userManager->getOneByName($user['name']);
                } else {
                    throw new \Exception("`\$user['name']` must not be empty when retrieving user from `\$user['name']`");
                }
            }
            // prevent setting cookies in CLI (could be errors)
            $rawData = $this->prepareRawData($currentDateTime, $remember, $user->getPassword());

            $passwordHasher = $this->passwordHasherFactory->getPasswordHasher('cookie');
            $encryptedData = $passwordHasher->hash($rawData);

            $expires = $this->getExpirationTimeStamp($currentDateTime, $remember);
            $this->setPersistentCookie('name', $user['name'], $expires);
            $this->setPersistentCookie('token', $currentDateTime->format(self::DATE_FORMAT_IN_TOKEN) . ($remember ? '1' : '0') . $encryptedData, $expires);

            // TODO : find a more secure way to autologin
            // (see https://www.php.net/manual/en/features.session.security.management.php#features.session.security.management.session-and-autologin)
        }
    }

    public function logout()
    {
        $this->cleanSensitiveDataFromSession();
        $this->cleanOldFormatCookie();
        if (!$this->wiki->isCli()) {
            // prevent setting cookies in CLI (could be errors)

            // delete cookies
            if (!empty($this->getRequest()->cookies->get('name'))) {
                $this->setPersistentCookie('name', '', time() - 3600);
                unset($_COOKIE['name']);
            }
            if (!empty($this->getRequest()->cookies->get('token'))) {
                $this->setPersistentCookie('token', '', time() - 3600);
                unset($_COOKIE['token']);
            }
        }
    }

    /**
     * connect the firstAdmin and return if
     * SHOULD NOT BE USED but, waiting an alternative, this hack exists.
     *
     * @return User|null $firtAdmin
     */
    public function connectFirstAdmin(): ?User
    {
        $firstAdminName = $this->wiki->services->get(UserOperationsService::class)->getFirstAdmin();
        if (empty($firstAdminName)) {
            return null;
        }
        $firstAdmin = $this->userManager->getOneByName($firstAdminName);
        if (empty($firstAdmin)) {
            return null;
        }
        $this->login($firstAdmin);

        return $firstAdmin;
    }

    /**
     * External auth extensions (CAS/LDAP/SSO) handle their own identity verification --
     * the email-activation gate in login() doesn't apply to accounts they authenticate.
     */
    private function hasLoginExtensions(): bool
    {
        return array_key_exists('logincas', $this->wiki->extensions)
            || array_key_exists('loginldap', $this->wiki->extensions)
            || array_key_exists('login-sso', $this->wiki->extensions);
    }

    private function updateSessionCookieExpires(int $expires)
    {
        $this->setPersistentCookie(session_name(), session_id(), $expires);
    }

    public function setPersistentCookie(string $name, string $value, int $expires)
    {
        $sessionParams = session_get_cookie_params();
        $newParams = array_filter($sessionParams, function ($v, $k) {
            return in_array($k, ['path', 'domain', 'secure', 'httponly', 'samesite']);
        }, ARRAY_FILTER_USE_BOTH);
        $newParams['expires'] = $expires;
        setcookie($name, $value, $newParams);
    }

    public function deleteOldCookie(string $name)
    {
        setcookie($name, '', [
            'path' => $this->wiki->CookiePath,
            'domain' => '',
            'secure' => $this->getRequest()->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
            'expires' => time() - 3600,
        ]);
        if ($this->getRequest()->cookies->has($name)) {
            unset($_COOKIE[$name]);
        }
    }

    /**
     * connect a user from COOKIE.
     *
     * @return array [
     *               'user' => User,
     *               'remember' => bool,
     *               'lastConnectionDate' => DateTime
     *               ]
     *
     * @throws BadUserConnectException
     */
    protected function connectUserFromCookies(): array
    {
        $data = $this->extractDataFromCookie();

        // check if user ever existing
        $user = $this->userManager->getOneByName($data->getUserName());

        if (empty($user)) {
            throw new BadUserConnectException('Unknown name');
        }

        $rawData = $this->prepareRawData($data->getLastConnectionDate(), $data->getRemember(), $user->getPassword());

        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($data);
        if (!$passwordHasher->verify($data->getEncryptedData(), $rawData)) {
            throw new BadUserConnectException('Wrong cookie');
        }

        return [
            'user' => $user,
            'remember' => $data->getRemember(),
            'lastConnectionDate' => $data->getLastConnectionDate(),
        ];
    }

    /**
     * connect a user from SESSION.
     *
     * @return array [
     *               'user' => User,
     *               'remember' => bool,
     *               'lastConnectionDate' => DateTime
     *               ]
     *
     * @throws BadUserConnectException
     */
    protected function connectUserFromSession(): array
    {
        $userFromSession = $this->getLoggedUser();
        if (empty($userFromSession['name'])) {
            throw new BadUserConnectException('No use in session');
        }

        // check if user ever existing
        $user = $this->userManager->getOneByName($userFromSession['name']);

        if (empty($user)) {
            throw new BadUserConnectException('Unknown name');
        }
        if (empty($userFromSession['lastConnection'])) {
            throw new BadUserConnectException('No last connection date');
        }

        $lastConnectionDate = \DateTime::createFromFormat('U', $userFromSession['lastConnection']);

        if ($lastConnectionDate === false || !($lastConnectionDate instanceof \DateTime)) {
            throw new BadUserConnectException('Last connection date badly formatted');
        }

        return [
            'user' => $user,
            'remember' => false, // force usage of cookies if more than 1 hour
            'lastConnectionDate' => $lastConnectionDate,
        ];
    }

    /**
     * extract data from cookies.
     *
     * @throws BadUserConnectException
     */
    protected function extractDataFromCookie(): CookieData
    {
        $cookies = $this->getRequest()->cookies;
        if (empty($cookies->get('name'))) {
            throw new BadUserConnectException('cookie \'name\' sould be set');
        }
        $userName = strval($cookies->get('name'));

        if (empty($cookies->get('token'))) {
            throw new BadUserConnectException('cookie \'token\' sould be set');
        }
        $token = strval($cookies->get('token'));
        if (strlen($token) <= self::DATE_LENGTH_IN_TOKEN) {
            throw new BadUserConnectException('cookie \'token\' is too short');
        }

        $lastConnectionDateStr = substr($token, 0, self::DATE_LENGTH_IN_TOKEN);
        $lastConnectionDate = \DateTime::createFromFormat(self::DATE_FORMAT_IN_TOKEN, $lastConnectionDateStr);

        if ($lastConnectionDate === false || !($lastConnectionDate instanceof \DateTime)) {
            throw new BadUserConnectException('cookie \'token\' does not begin by a date');
        }

        $remember = (substr($token, self::DATE_LENGTH_IN_TOKEN, 1) === '1');

        $encryptedData = substr($token, self::DATE_LENGTH_IN_TOKEN + 1);

        return new CookieData($userName, $lastConnectionDate, $remember, $encryptedData);
    }

    /**
     * prepare raw data from $lastConnectionDate, $remember, $hashedPassword.
     */
    protected function prepareRawData(\DateTime $lastConnectionDate, bool $remember, string $hashedPassword): string
    {
        return $hashedPassword . $lastConnectionDate->format(self::DATE_FORMAT_IN_TOKEN) . ($remember ? '1' : '0');
    }

    /**
     * clean sensitive data from session.
     */
    protected function cleanSensitiveDataFromSession()
    {
        if (!empty($_SESSION['user']['name'])) {
            // clean '_csrf' only if a user was connected before
            if (isset($_SESSION['_csrf'])) {
                unset($_SESSION['_csrf']);
            }
        }
        if (isset($_SESSION['user'])) {
            unset($_SESSION['user']);
        }
        $this->loggedUserCache = null;
    }

    /**
     * clean auth cookie for old format.
     */
    protected function cleanOldFormatCookie()
    {
        if (!$this->wiki->isCli()) {
            if (!empty($this->getRequest()->cookies->get('password'))) {
                $this->deleteOldCookie('password');
            }
            if (!empty($this->getRequest()->cookies->get('remember'))) {
                $this->deleteOldCookie('remember');
            }
            // update session cookies to be only for session
            $this->updateSessionCookieExpires(0);
        }
    }
}
