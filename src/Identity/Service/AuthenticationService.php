<?php

namespace YesWiki\Identity\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Exception\BadFormatPasswordException;
use YesWiki\Identity\Exception\BadLoginException;
use YesWiki\Identity\Exception\BadUserConnectException;
use YesWiki\Identity\Security\LegacyPasswordHash;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Kernel\Entity\CookieData;
use YesWiki\Kernel\Service\ActorSource;
use YesWiki\Kernel\Service\CachesTheDatabase;
use YesWiki\Kernel\Service\Flash;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\PersonalResponse;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Kernel\Service\WikiCookies;

#[AsAlias(ActorSource::class, public: true)]
class AuthenticationService extends YesWikiController implements ActorSource, CachesTheDatabase
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

    /** @var array{name: string, issued: int, remember: bool, generation: int}|null who this request acts for, read from `yw-auth` or set by login() */
    private ?array $signedIn = null;

    public const GENERATION_PROPERTY = 'auth_generation';

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

    public function startNewRequest(): void
    {
        $this->loggedUserCache = null;
        if (!$this->isCli()) {
            $this->signedIn = null;
        }
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
        return $user->provider() === null && LegacyPasswordHash::isMd5($user->getPassword());
    }

    /** What to tell someone whose stored password is an md5: the lost-password link, unless this wiki sends no such mail. */
    public function obsoletePasswordMessage(): string
    {
        return boolval($this->params->get('contact_disable_email_for_password')) ? _t('LOGIN_PASSWORD_FORMAT_OBSOLETE_ASK_ADMIN') : _t('LOGIN_PASSWORD_FORMAT_OBSOLETE');
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
        $this->revokeEverySignIn($user->getName());
    }

    /** Signs the account out of every browser at their next request; this one stays signed in when it is the account's own. */
    public function revokeEverySignIn(string $name): void
    {
        $generation = $this->generationOf($name) + 1;
        $tripleStore = $this->container->get(TripleStore::class);
        $tripleStore->delete($name, self::GENERATION_PROPERTY);
        $tripleStore->create($name, self::GENERATION_PROPERTY, (string)$generation);
        if (($this->signedIn['name'] ?? null) === $name) {
            $this->signedIn['generation'] = $generation;
            $this->writeLoginCookie();
        }
    }

    /** Who this process acts for, with no cookie: a command, or a test; null for nobody. */
    public function actAs(?string $name): void
    {
        $this->loggedUserCache = null;
        $this->signedIn = $name === null || $name === ''
            ? null
            : ['name' => $name, 'issued' => time(), 'remember' => false, 'generation' => $this->generationOf($name)];
    }

    /** The signed-in user's name, '' for nobody, never an address. */
    public function signedInName(): string
    {
        return $this->signedIn['name'] ?? '';
    }

    /** The generation of the signed-in account's sign-ins, 0 for nobody: what a CSRF token is bound to, so revoking the one revokes the other. */
    public function signedInGeneration(): int
    {
        return $this->signedIn['generation'] ?? 0;
    }

    private function generationOf(string $name): int
    {
        $stored = $this->container->get(TripleStore::class)->getOne($name, self::GENERATION_PROPERTY);

        return max(1, (int)$stored);
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

    /** Finds who this request is for: the `yw-auth` cookie, checked by HMAC; for one release the old `name` and `token` pair, checked once by bcrypt and traded for one. */
    public function connectUser(): void
    {
        $this->cleanOldFormatCookie();
        if ($this->isCli()) {
            if ($this->signedIn !== null && $this->stillValid($this->signedIn['name'], $this->signedIn['generation']) === null) {
                $this->actAs(null);
            }

            return;
        }
        try {
            if ($this->connectUserFromLoginCookie()) {
                return;
            }
            $data = $this->connectUserFromCookies();
            if ($this->getExpirationTimeStamp($data['lastConnectionDate'], $data['remember']) < time()) {
                throw new BadUserConnectException('The old cookie has expired');
            }
            $this->login($data['user'], $data['remember'] ? 1 : 0);
        } catch (BadUserConnectException $th) {
            $this->logout();
        } catch (BadLoginException $th) {
            Flash::error($th->getMessage());
            $this->logout();
        }
    }

    /** Signs the request in from a valid `yw-auth` and renews it once it is half-way to its expiry; false when there is none, after deleting one that is no longer good. */
    private function connectUserFromLoginCookie(): bool
    {
        $cookie = $this->getRequest()->cookies->get(LoginCookie::NAME);
        if (!is_string($cookie) || $cookie === '') {
            return false;
        }
        $now = time();
        $read = $this->container->get(LoginCookie::class)->read($cookie, $now);
        $user = $read === null ? null : $this->stillValid($read['name'], $read['generation']);
        if ($read === null || $user === null) {
            $this->setPersistentCookie(LoginCookie::NAME, '', $now - 3600);
            unset($_COOKIE[LoginCookie::NAME]);

            return false;
        }
        $this->loggedUserCache = null;
        $this->signedIn = ['name' => $read['name'], 'issued' => $read['issued'], 'remember' => $read['remember'], 'generation' => $read['generation']];
        $this->container->get(PersonalResponse::class)->mark();
        if (LoginCookie::isDueForRenewal($read, $now)) {
            $this->signedIn['issued'] = $now;
            $this->writeLoginCookie();
        }

        return true;
    }

    /** The account a sign-in names, when it still exists, may sign in and has not been revoked since; null otherwise. */
    private function stillValid(string $name, int $generation): ?User
    {
        $user = $this->userManager->getOneByName($name);
        if ($user === null || $this->requiresPasswordReset($user) || $generation !== $this->generationOf($name)) {
            return null;
        }
        if (!$this->placeIsOn($user)) {
            return null;
        }

        return $user;
    }

    /** Whether the place the account belongs to, local or a provider, is switched on and installed: every way of staying signed in asks. */
    private function placeIsOn(User $user): bool
    {
        $registry = $this->container->get(ProviderRegistry::class);

        return $user->provider() === null ? $registry->localEnabled() : $registry->isEnabled($user->provider());
    }

    /** Sends the `yw-auth` cookie for whoever is signed in now. */
    private function writeLoginCookie(): void
    {
        if ($this->isCli() || $this->signedIn === null) {
            return;
        }
        $now = $this->signedIn['issued'];
        $value = $this->container->get(LoginCookie::class)->issue($this->signedIn['name'], $this->signedIn['remember'], $this->signedIn['generation'], $now);
        $this->setPersistentCookie(LoginCookie::NAME, $value, $now + ($this->signedIn['remember'] ? LoginCookie::REMEMBERED_LIFETIME : LoginCookie::SHORT_LIFETIME));
        $_COOKIE[LoginCookie::NAME] = $value;
    }

    /**
     * The signed-in user as an array, or '' when nobody is signed in.
     *
     * @return array<string, mixed>|''
     */
    public function getLoggedUser()
    {
        $name = $this->signedInName();
        if ($name === '') {
            return '';
        }

        if (!is_array($this->loggedUserCache) || $this->loggedUserCache['name'] !== $name) {
            $user = $this->userManager->getOneByName($name);
            if (!empty($user)) {
                $this->loggedUserCache = $user->getArrayCopy();
            } else {
                $this->loggedUserCache = '';
            }
        }

        return $this->loggedUserCache;
    }

    /** The signed-in user's name, or -- for an anonymous visitor -- their IP address, by a convention as old as the wiki. */
    /**
     * A sign-in that did not work, recorded as an act (ticket 51).
     *
     * The actor is whoever tried, which for a visitor with no session is their address -- so a
     * hundred failures from one place read as a hundred rows naming it, which is the whole reason
     * audit entries never collapse.
     */
    public function recordFailedLogin(string $username, string $reason, ?string $provider = null): void
    {
        $context = ['reason' => $reason] + ($provider === null ? [] : ['provider' => $provider]);
        $this->getService(Journal::class)->audit('login.failed', $username, $context, null, LogLevel::WARNING);
    }

    /** ActorSource: the Journal names whoever acted, and off a request there is nobody to name. */
    public function currentActor(): string
    {
        return $this->getLoggedUserName();
    }

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
            && !$this->isLinked($user, $userName)
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

        $previousUserName = $this->signedIn['name'] ?? null;
        if ($previousUserName !== null && $previousUserName != $user['name']) {
            $this->cleanSensitiveDataFromSession();
        }
        $remember = filter_var($remember, FILTER_VALIDATE_BOOL);

        if (empty($user['name'])) {
            $this->actAs(null);

            return;
        }
        $this->loggedUserCache = null;
        $this->signedIn = ['name' => (string)$user['name'], 'issued' => time(), 'remember' => $remember, 'generation' => $this->generationOf((string)$user['name'])];
        $this->container->get(PersonalResponse::class)->mark();

        if ($user['name'] !== $previousUserName && !$this->isCli() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        if (!$this->isCli()) {
            if (!$user instanceof User) {
                $storedUser = $this->userManager->getOneByName($user['name']);
                if ($storedUser === null) {
                    throw new \Exception("no user named `{$user['name']}` to sign in");
                }
                $user = $storedUser;
            }

            $this->writeLoginCookie();
            $this->forgetTheOldCookies();
        }
    }

    /** Deletes the `name` and `token` pair a sign-in made before `yw-auth`. */
    private function forgetTheOldCookies(): void
    {
        foreach (['name', 'token'] as $old) {
            if (!empty($this->getRequest()->cookies->get($old))) {
                $this->setPersistentCookie($old, '', time() - 3600);
                unset($_COOKIE[$old]);
            }
        }
    }

    public function logout(): void
    {
        $this->cleanSensitiveDataFromSession();
        $this->signedIn = null;
        $this->cleanOldFormatCookie();
        if (!$this->isCli()) {
            $this->forgetTheOldCookies();
            if (!empty($this->getRequest()->cookies->get(LoginCookie::NAME))) {
                $this->setPersistentCookie(LoginCookie::NAME, '', time() - 3600);
                unset($_COOKIE[LoginCookie::NAME]);
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

    /** An account tied to a sign-in provider has been vouched for by it: the email-activation gate is for local accounts. */
    private function isLinked(array|User $user, string $name): bool
    {
        $stored = $user instanceof User ? $user : $this->userManager->getOneByName($name);

        return $stored?->provider() !== null;
    }

    public function setPersistentCookie(string $name, string $value, int $expires): void
    {
        $this->container->get(WikiCookies::class)->set($name, $value, $expires);
    }

    /**
     * How the wiki's own cookies are sent: its path, secure on https, never to scripts, and the SameSite the configuration asks for.
     *
     * @return array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'None'|'Strict'}
     */
    public function cookieOptions(int $expires): array
    {
        return $this->container->get(WikiCookies::class)->options($expires);
    }

    public function deleteOldCookie(string $name): void
    {
        $this->setPersistentCookie($name, '', time() - 3600);
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

        if (!$this->placeIsOn($user)) {
            throw new BadUserConnectException('The place this account signs in through is switched off');
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
        }
    }
}
