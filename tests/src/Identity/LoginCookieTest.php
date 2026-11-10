<?php

namespace YesWiki\Test\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\LoginCookie;
use YesWiki\Identity\Service\PasswordHasherFactory;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\SignedTokens;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 12: a sign-in is one cookie any replica checks with an HMAC, and an account can take back every one of them at once. */
#[CoversClass(LoginCookie::class)]
#[CoversClass(AuthenticationService::class)]
class LoginCookieTest extends YesWikiTestCase
{
    private const NAME = 'LoginCookieTestUser';

    private Request $requestWas;

    /** @var array<string, mixed> */
    private array $cookiesWere;

    private int $verified = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->requestWas = $services->get(CurrentRequest::class)->get();
        $this->cookiesWere = $_COOKIE;
        $users = $services->get(UserManager::class);
        if ($users->getOneByName(self::NAME) === null) {
            $users->create(self::NAME, 'logincookietest@example.com', 'a-long-enough-password');
        }
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        $services->get(CurrentRequest::class)->replace($this->requestWas);
        $_COOKIE = $this->cookiesWere;
        self::actAs(null);
        $users = $services->get(UserManager::class);
        if ($user = $users->getOneByName(self::NAME)) {
            $users->delete($user);
        }
        parent::tearDown();
    }

    public function testACookieReadsBackAsItWasIssued(): void
    {
        $cookies = self::getWiki()->services->get(LoginCookie::class);
        $value = $cookies->issue(self::NAME, true, 3, 1_000_000);

        $this->assertSame(['name' => self::NAME, 'issued' => 1_000_000, 'expires' => 1_000_000 + LoginCookie::REMEMBERED_LIFETIME, 'remember' => true, 'generation' => 3], $cookies->read($value, 1_000_001));
    }

    public function testAnEditedOrForeignOrExpiredCookieIsRefused(): void
    {
        $cookies = self::getWiki()->services->get(LoginCookie::class);
        $value = $cookies->issue(self::NAME, false, 1, 1_000_000);
        [$payload, $signature] = explode('.', $value);
        $edited = rtrim(strtr(base64_encode((string)json_encode(['n' => 'WikiAdmin', 'i' => 1_000_000, 'e' => 1_003_600, 'r' => 0, 'g' => 1])), '+/', '-_'), '=');
        $elsewhere = sys_get_temp_dir() . '/yw-other-key-' . bin2hex(random_bytes(4));
        $foreign = (new LoginCookie(new SignedTokens(Storage::rootedAt($elsewhere))))->issue(self::NAME, false, 1, 1_000_000);

        $this->assertNull($cookies->read($edited . '.' . $signature, 1_000_001), 'a cookie edited by hand');
        $this->assertNull($cookies->read($foreign, 1_000_001), 'a cookie another wiki signed');
        $this->assertNull($cookies->read($value, 1_000_000 + LoginCookie::SHORT_LIFETIME + 1), 'a cookie past its expiry');
        $this->assertNull($cookies->read($payload, 1_000_001), 'a cookie with no signature');
    }

    public function testACookieIsRenewedOnceHalfWayToItsExpiry(): void
    {
        $read = ['issued' => 1_000_000, 'expires' => 1_003_600];

        $this->assertFalse(LoginCookie::isDueForRenewal($read, 1_001_000));
        $this->assertTrue(LoginCookie::isDueForRenewal($read, 1_002_000));
    }

    public function testTheNextRequestIsSignedInFromTheCookieWithoutABcrypt(): void
    {
        $user = self::getWiki()->services->get(UserManager::class)->getOneByName(self::NAME);
        $this->assertNotNull($user);
        $this->browser()->login($user, 1);
        $cookie = $_COOKIE[LoginCookie::NAME] ?? null;
        $this->assertIsString($cookie, 'signing in sends yw-auth');

        $this->verified = 0;
        $next = $this->nextRequest([LoginCookie::NAME => $cookie]);

        $this->assertSame(self::NAME, $next->signedInName());
        $this->assertSame(0, $this->verified, 'an HMAC, not a bcrypt, on every request');
    }

    public function testChangingThePasswordSignsTheOtherBrowsersOut(): void
    {
        $users = self::getWiki()->services->get(UserManager::class);
        $user = $users->getOneByName(self::NAME);
        $this->assertNotNull($user);
        $this->browser()->login($user);
        $otherBrowser = (string)$_COOKIE[LoginCookie::NAME];

        $here = $this->browser();
        $here->login($user);
        $here->setPassword($user, 'another-long-enough-password');
        $renewed = (string)$_COOKIE[LoginCookie::NAME];

        $this->assertSame('', $this->nextRequest([LoginCookie::NAME => $otherBrowser])->signedInName(), 'the other browser is signed out');
        $this->assertSame(self::NAME, $this->nextRequest([LoginCookie::NAME => $renewed])->signedInName(), 'and this one is not');
    }

    public function testSomebodySignedInBeforeTheUpgradeStaysSignedIn(): void
    {
        $users = self::getWiki()->services->get(UserManager::class);
        $user = $users->getOneByName(self::NAME);
        $this->assertNotNull($user);
        $date = new \DateTime('-5 minutes');
        $raw = (string)$user->getPassword() . $date->format('Ymd-H-i-s') . '0';
        $token = $date->format('Ymd-H-i-s') . '0' . self::getWiki()->services->get(PasswordHasherFactory::class)->getPasswordHasher('cookie')->hash($raw);
        unset($_COOKIE[LoginCookie::NAME]);

        $next = $this->nextRequest(['name' => self::NAME, 'token' => $token]);

        $this->assertSame(self::NAME, $next->signedInName(), 'the old pair is checked once');
        $this->assertIsString($_COOKIE[LoginCookie::NAME] ?? null, 'and traded for yw-auth');
    }

    /** @param array<string, string> $cookies */
    private function nextRequest(array $cookies): AuthenticationService
    {
        $_COOKIE = $cookies;
        self::getWiki()->services->get(CurrentRequest::class)->replace(new Request([], [], [], $cookies));
        $browser = $this->browser();
        $browser->connectUser();

        return $browser;
    }

    /** The service as a web request has it, with a hasher factory that counts the bcrypt checks. */
    private function browser(): AuthenticationService
    {
        $services = self::getWiki()->services;
        $count = function (): void {
            $this->verified++;
        };
        $hashers = new class($count) extends PasswordHasherFactory {
            public function __construct(private readonly \Closure $count)
            {
                parent::__construct('test');
            }

            public function getPasswordHasher(\Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface|\Symfony\Component\PasswordHasher\Hasher\PasswordHasherAwareInterface|string $user): PasswordHasherInterface
            {
                ($this->count)();

                return parent::getPasswordHasher($user);
            }
        };

        $browser = new class($services->get(AccountActivationService::class), $services->get(HibernationService::class), $services->get(ParameterBagInterface::class), $hashers, $services->get(UserManager::class), $services) extends AuthenticationService {
            protected function isCli(): bool
            {
                return false;
            }
        };
        $browser->setServices($services);

        return $browser;
    }
}
