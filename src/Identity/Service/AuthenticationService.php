<?php

namespace YesWiki\Identity\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Exception\BadFormatPasswordException;
use YesWiki\Identity\Exception\BadLoginException;
use YesWiki\Identity\Exception\BadUserConnectException;
use YesWiki\Identity\Security\LegacyPasswordHash;
use YesWiki\Kernel\Entity\CookieData;
use YesWiki\Kernel\Service\HibernationService;

class AuthenticationService extends YesWikiController
{
    use LimitationsTrait;

    public const DEFAULT_PASSWORD_MINIMUM_LENGTH = 5;
    protected const DATE_LENGTH_IN_TOKEN = 17;
    protected const DATE_FORMAT_IN_TOKEN = 'Ymd-H-i-s';

    /** @var array<string, mixed> */
    private array $limitations = [];
    protected AccountActivationService $accountActivationService;
    protected ParameterBagInterface $params;
    protected PasswordHasherFactory $passwordHasherFactory;
    protected HibernationService $hibernationService;
    protected UserManager $userManager;
    /** @var array<string, mixed>|string|null */
    private $loggedUserCache;

    /** Overridable seam: tests exercise the non-CLI branches under the CLI SAPI. */
    protected function isCli(): bool
    {
        return \YesWiki\Core\YesWikiKernel::isCli();
    }

    protected ContainerInterface $container;

    public function __construct(
        AccountActivationService $accountActivationService,
        HibernationService $hibernationService,
        ParameterBagInterface $params,
        PasswordHasherFactory $passwordHasherFactory,
        UserManager $userManager,
        ContainerInterface $container
    ) {
        $this->accountActivationService = $accountActivationService;
        $this->hibernationService = $hibernationService;
        $this->params = $params;
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->userManager = $userManager;
        $this->container = $container;
        $this->initLimitations();
    }

    /**
     * Initializes object limitation properties using values from the config file.
     *
     * @return void
     */
    private function initLimitations()
    {
        $this->limitations = [];
        $this->initLimitationHelper(
            'user_password_min_length',
            'passwordMinimumLength',
            self::DEFAULT_PASSWORD_MINIMUM_LENGTH,
            'USER_PASSWORD_MIN_LENGTH_NOT_INT'
        );
    }

    /**
     * Whether this account's stored password is an md5 written by an older YesWiki, and so cannot be used to sign in until its owner resets it.
     */
    public function requiresPasswordReset(User $user): bool
    {
        return LegacyPasswordHash::isMd5($user->getPassword());
    }

    /**
     * checks if the given string is the user's password.
     *
     * @return bool True if OK or false if any problems
     */
    public function checkPassword(string $plainTextPassword, User $user)
    {
        if ($this->requiresPasswordReset($user)) {
            return false;
        }

        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);
        $hashedPassword = $user->getPassword();
        if ($hashedPassword === null || !$passwordHasher->verify($hashedPassword, $plainTextPassword)) {
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
    public function setPassword(User $user, string $plainTextPassword): void
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

    /** connect a user from SESSION or COOKIES. */
    public function connectUser(): void
    {
        $this->cleanOldFormatCookie();
        try {
            try {
                $data = $this->connectUserFromSession();
                if ($this->getExpirationTimeStamp($data['lastConnectionDate'], $data['remember']) < time()) {
                    throw new BadUserConnectException('Not connected via session');
                }
            } catch (BadUserConnectException $th) {
                $data = $this->connectUserFromCookies();
                if ($this->getExpirationTimeStamp($data['lastConnectionDate'], $data['remember']) < time()) {
                    $this->logout();
                }
            }

            $this->login($data['user'], $data['remember'] ? 1 : 0);
        } catch (BadUserConnectException $th) {
            if (
                empty($_SESSION['user']['name'])
                || empty($data['user']['name'])
                || $data['user']['name'] != $_SESSION['user']['name']
                || !$this->container->get(AclService::class)->isAdmin($data['user']['name'])
            ) {
                $this->logout();
            }
        } catch (BadLoginException $th) {
            Flash::error($th->getMessage());
            $this->logout();
        }
    }

    /**
     * The signed-in user as an array, or '' when nobody is signed in.
     *
     * @return array<string, mixed>|''
     */
    public function getLoggedUser()
    {
        if (!isset($_SESSION['user']) || empty($_SESSION['user']['name'])) {
            return '';
        }

        if (!is_array($this->loggedUserCache) || $this->loggedUserCache['name'] !== $_SESSION['user']['name']) {
            $user = $this->userManager->getOneByName($_SESSION['user']['name']);
            if (!empty($user)) {
                $this->loggedUserCache = $user->getArrayCopy();
            } else {
                $this->loggedUserCache = '';
            }
        }

        return $this->loggedUserCache;
    }

    /** The signed-in user's name, or -- for an anonymous visitor -- their IP address, by a convention as old as the wiki. */
    public function getLoggedUserName(): string
    {
        if ($user = $this->getLoggedUser()) {
            return strval($user['name'] ?? '');
        }

        return $this->isCli() ? '' : ($this->getRequest()->getClientIp() ?? '');
    }

    public function getExpirationTimeStamp(\DateTime $startTime, bool $remember): int
    {
        return $startTime->getTimestamp() + ($remember ? 90 * 24 * 60 * 60 : 60 * 60);
    }

    /**
     * @throws BadLoginException if signup_email_activation is on and this user isn't
     *                           activated yet (an activation email is (re-)sent as a
     *                           side effect)
     */
    /**
     * @param array<string, mixed>|User $user
     * @param bool|int|string           $remember
     */
    public function login($user, $remember = 0): void
    {
        $userName = empty($user['name']) ? null : $user['name'];
        if (
            $userName !== null

            && in_array($this->params->get('signup_email_activation'), [1, true, '1', 'true'], true)
            && !$this->hasLoginExtensions()
            && !$this->container->get(AclService::class)->isAdmin($userName)
            && !$this->accountActivationService->isActivated($userName)
            && $this->container->get(AccountJustCreated::class)->name() !== $userName
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

        if (!empty($user['name']) && $user['name'] !== $previousUserName && !$this->isCli()) {
            session_regenerate_id(true);
        }

        if (!$this->isCli()) {
            if (!$user instanceof User) {
                if (empty($user['name'])) {
                    throw new \Exception("`\$user['name']` must not be empty when retrieving user from `\$user['name']`");
                }
                $storedUser = $this->userManager->getOneByName($user['name']);
                if ($storedUser === null) {
                    throw new \Exception("no user named `{$user['name']}` to sign in");
                }
                $user = $storedUser;
            }

            $hashedPassword = $user->getPassword();
            if ($hashedPassword === null) {
                throw new \Exception("user `{$user->getName()}` has no password to sign the auth cookie with");
            }
            $rawData = $this->prepareRawData($currentDateTime, $remember, $hashedPassword);

            $passwordHasher = $this->passwordHasherFactory->getPasswordHasher('cookie');
            $encryptedData = $passwordHasher->hash($rawData);

            $expires = $this->getExpirationTimeStamp($currentDateTime, $remember);
            $this->setPersistentCookie('name', $user->getName(), $expires);
            $this->setPersistentCookie('token', $currentDateTime->format(self::DATE_FORMAT_IN_TOKEN) . ($remember ? '1' : '0') . $encryptedData, $expires);
        }
    }

    public function logout(): void
    {
        $this->cleanSensitiveDataFromSession();
        $this->cleanOldFormatCookie();
        if (!$this->isCli()) {
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
     * connect the firstAdmin and return if SHOULD NOT BE USED but, waiting an alternative, this hack exists.
     *
     * @return User|null $firtAdmin
     */
    public function connectFirstAdmin(): ?User
    {
        $firstAdminName = $this->container->get(UserOperationsService::class)->getFirstAdmin();
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
     * External auth extensions (CAS/LDAP/SSO) handle their own identity verification -- the email-activation gate in login() doesn't apply to accounts they authenticate.
     */
    private function hasLoginExtensions(): bool
    {
        return array_key_exists('logincas', $this->container->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all())
            || array_key_exists('loginldap', $this->container->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all())
            || array_key_exists('login-sso', $this->container->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all());
    }

    private function updateSessionCookieExpires(int $expires): void
    {
        $sessionName = session_name();
        $sessionId = session_id();
        if ($sessionName === false || $sessionId === false) {
            return;
        }

        $this->setPersistentCookie($sessionName, $sessionId, $expires);
    }

    public function setPersistentCookie(string $name, string $value, int $expires): void
    {
        $sessionParams = session_get_cookie_params();
        $newParams = array_filter($sessionParams, function ($v, $k) {
            return in_array($k, ['path', 'domain', 'secure', 'httponly', 'samesite']);
        }, ARRAY_FILTER_USE_BOTH);
        $newParams['expires'] = $expires;
        setcookie($name, $value, $newParams);
    }

    public function deleteOldCookie(string $name): void
    {
        $cookiePath = $this->params->get('cookie_path');
        setcookie($name, '', [
            'path' => is_string($cookiePath) ? $cookiePath : '/',
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
     * @return array{user: User, remember: bool, lastConnectionDate: \DateTime}
     *
     * @throws BadUserConnectException
     */
    protected function connectUserFromCookies(): array
    {
        $data = $this->extractDataFromCookie();

        $user = $this->userManager->getOneByName($data->getUserName());

        if (empty($user)) {
            throw new BadUserConnectException('Unknown name');
        }

        if ($this->requiresPasswordReset($user)) {
            throw new BadUserConnectException('Password predates this version, must be reset');
        }

        $hashedPassword = $user->getPassword();
        if ($hashedPassword === null) {
            throw new BadUserConnectException('Account has no password');
        }

        $rawData = $this->prepareRawData($data->getLastConnectionDate(), $data->getRemember(), $hashedPassword);

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
     * @return array{user: User, remember: bool, lastConnectionDate: \DateTime}
     *
     * @throws BadUserConnectException
     */
    protected function connectUserFromSession(): array
    {
        $userFromSession = $this->getLoggedUser();
        if (empty($userFromSession['name'])) {
            throw new BadUserConnectException('No use in session');
        }

        $user = $this->userManager->getOneByName($userFromSession['name']);

        if (empty($user)) {
            throw new BadUserConnectException('Unknown name');
        }

        if ($this->requiresPasswordReset($user)) {
            throw new BadUserConnectException('Password predates this version, must be reset');
        }

        $lastConnection = $_SESSION['user']['lastConnection'] ?? null;
        if (empty($lastConnection)) {
            throw new BadUserConnectException('No last connection date');
        }

        $lastConnectionDate = \DateTime::createFromFormat('U', (string)$lastConnection);

        if ($lastConnectionDate === false) {
            throw new BadUserConnectException('Last connection date badly formatted');
        }

        return [
            'user' => $user,
            'remember' => false,
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

        if ($lastConnectionDate === false) {
            throw new BadUserConnectException('cookie \'token\' does not begin by a date');
        }

        $remember = (substr($token, self::DATE_LENGTH_IN_TOKEN, 1) === '1');

        $encryptedData = substr($token, self::DATE_LENGTH_IN_TOKEN + 1);

        return new CookieData($userName, $lastConnectionDate, $remember, $encryptedData);
    }

    /** prepare raw data from $lastConnectionDate, $remember, $hashedPassword. */
    protected function prepareRawData(\DateTime $lastConnectionDate, bool $remember, string $hashedPassword): string
    {
        return $hashedPassword . $lastConnectionDate->format(self::DATE_FORMAT_IN_TOKEN) . ($remember ? '1' : '0');
    }

    /** clean sensitive data from session. */
    protected function cleanSensitiveDataFromSession(): void
    {
        if (!empty($_SESSION['user']['name'])) {
            if (isset($_SESSION['_csrf'])) {
                unset($_SESSION['_csrf']);
            }
        }
        if (isset($_SESSION['user'])) {
            unset($_SESSION['user']);
        }
        $this->loggedUserCache = null;
    }

    /** clean auth cookie for old format. */
    protected function cleanOldFormatCookie(): void
    {
        if (!$this->isCli()) {
            if (!empty($this->getRequest()->cookies->get('password'))) {
                $this->deleteOldCookie('password');
            }
            if (!empty($this->getRequest()->cookies->get('remember'))) {
                $this->deleteOldCookie('remember');
            }

            $this->updateSessionCookieExpires(0);
        }
    }
}
