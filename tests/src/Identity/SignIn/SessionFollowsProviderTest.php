<?php

namespace YesWiki\Test\Identity\SignIn;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\LoginCookie;
use YesWiki\Identity\Service\PasswordHasherFactory;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once __DIR__ . '/FakeProviderTypes.php';

/** An account signs in only while the place it belongs to is switched on. */
class SessionFollowsProviderTest extends YesWikiTestCase
{
    use FakeProviderTypes;

    private const TAGS = ['SessionLocal', 'SessionLinked', 'SessionMd5Linked'];

    private Request $requestWas;

    /** @var array<string, mixed> */
    private array $cookiesWere;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->requestWas = $services->get(CurrentRequest::class)->get();
        $this->cookiesWere = $_COOKIE;
        $this->cleanUp();
        $users = $services->get(UserManager::class);
        $users->create('SessionLocal', 'session-local@example.com', 'a-long-enough-password');
        $links = $services->get(AccountLinks::class);
        foreach (['SessionLinked' => 'session-linked@example.com', 'SessionMd5Linked' => 'session-md5@example.com'] as $tag => $email) {
            $user = $users->create($tag, $email, 'a-long-enough-password');
            $this->assertNotNull($user);
            $links->link($user, 'acme', 'subject-' . $tag);
        }
    }

    protected function tearDown(): void
    {
        $this->restoreProviders();
        $services = self::getWiki()->services;
        $services->get(CurrentRequest::class)->replace($this->requestWas);
        $_COOKIE = $this->cookiesWere;
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $services = self::getWiki()->services;
        $users = $services->get(UserManager::class);
        foreach (self::TAGS as $tag) {
            if ($user = $users->getOneByName($tag)) {
                $users->delete($user);
            }
            $services->get(\YesWiki\Content\Service\PageManager::class)->deleteOrphaned($tag);
        }
    }

    private function browser(?ParameterBagInterface $params = null): AuthenticationService
    {
        $services = self::getWiki()->services;
        $browser = new class($services->get(AccountActivationService::class), $services->get(HibernationService::class), $params ?? $services->get(ParameterBagInterface::class), $services->get(PasswordHasherFactory::class), $services->get(UserManager::class), $services) extends AuthenticationService {
            protected function isCli(): bool
            {
                return false;
            }
        };
        $browser->setServices($services);

        return $browser;
    }

    /** Signs the account in, then reads the cookie it was given on the next request. */
    private function connectFromCookie(string $tag): AuthenticationService
    {
        $user = self::getWiki()->services->get(UserManager::class)->getOneByName($tag);
        $this->assertNotNull($user);
        $this->browser()->login($user, 1);
        $cookie = (string)$_COOKIE[LoginCookie::NAME];

        $_COOKIE = [LoginCookie::NAME => $cookie];
        self::getWiki()->services->get(CurrentRequest::class)->replace(new Request([], [], [], $_COOKIE));
        $next = $this->browser();
        $next->connectUser();

        return $next;
    }

    public function testAnAccountOnAnEnabledProviderStaysSignedIn(): void
    {
        $this->installProviders(['acme' => ['type' => 'fakeredirect'], 'local' => ['type' => 'local']]);
        $this->assertSame('SessionLinked', $this->connectFromCookie('SessionLinked')->signedInName());
    }

    public function testAnAccountOnASwitchedOffProviderLosesItsSignIn(): void
    {
        $this->installProviders(['acme' => ['type' => 'fakeredirect', 'enabled' => false], 'local' => ['type' => 'local']]);
        $this->assertSame('', $this->connectFromCookie('SessionLinked')->signedInName());
    }

    public function testALocalAccountLosesItsSignInWhenLocalIsOff(): void
    {
        $this->installProviders(['acme' => ['type' => 'fakeredirect'], 'local' => ['type' => 'local', 'enabled' => false]]);
        $this->assertSame('', $this->connectFromCookie('SessionLocal')->signedInName());
    }

    public function testALocalAccountStaysSignedInWhileLocalIsOn(): void
    {
        $this->installProviders(['acme' => ['type' => 'fakeredirect'], 'local' => ['type' => 'local']]);
        $this->assertSame('SessionLocal', $this->connectFromCookie('SessionLocal')->signedInName());
    }

    public function testAnUnregisteredTypeEndsTheSignInToo(): void
    {
        $this->installProviders(['acme' => ['type' => 'a-type-that-is-gone'], 'local' => ['type' => 'local']]);
        $this->assertSame('', $this->connectFromCookie('SessionLinked')->signedInName());
    }

    public function testALinkedAccountWithAnMd5LeftoverStaysSignedIn(): void
    {
        $services = self::getWiki()->services;
        $users = $services->get(UserManager::class);
        $user = $users->getOneByName('SessionMd5Linked');
        $this->assertNotNull($user);
        $users->upgradePassword($user, md5('left over'));
        $user = $users->getOneByName('SessionMd5Linked');
        $this->assertNotNull($user);

        $this->assertFalse($services->get(AuthenticationService::class)->requiresPasswordReset($user));
        $this->installProviders(['acme' => ['type' => 'fakeredirect']]);
        $this->assertSame('SessionMd5Linked', $this->connectFromCookie('SessionMd5Linked')->signedInName());
    }

    public function testALocalMd5AccountStillNeedsAReset(): void
    {
        $services = self::getWiki()->services;
        $users = $services->get(UserManager::class);
        $user = $users->getOneByName('SessionLocal');
        $this->assertNotNull($user);
        $users->upgradePassword($user, md5('left over'));

        $this->assertTrue($services->get(AuthenticationService::class)->requiresPasswordReset($users->getOneByName('SessionLocal')));
    }

    public function testActivationGateSkipsLinkedAccounts(): void
    {
        $services = self::getWiki()->services;
        $users = $services->get(UserManager::class);
        $params = new class($services->get(ParameterBagInterface::class)) extends ParameterBag {
            public function __construct(private ParameterBagInterface $real)
            {
                parent::__construct();
            }

            public function get(string $name): array|bool|string|int|float|\UnitEnum|null
            {
                return $name === 'signup_email_activation' ? '1' : $this->real->get($name);
            }
        };
        $browser = $this->browser($params);

        $local = $users->getOneByName('SessionLocal');
        $linked = $users->getOneByName('SessionLinked');
        $this->assertNotNull($local);
        $this->assertNotNull($linked);

        $browser->login($linked, 1);
        $this->assertSame('SessionLinked', $browser->signedInName(), 'a linked account has no email to activate');

        $this->expectException(\YesWiki\Identity\Exception\BadLoginException::class);
        $browser->login($local, 1);
    }

    /** The pair of cookies `name` and `token` that wikis wrote before yw-auth. */
    private function connectFromOldCookies(string $tag): AuthenticationService
    {
        $services = self::getWiki()->services;
        $user = $services->get(UserManager::class)->getOneByName($tag);
        $this->assertNotNull($user);
        $date = new \DateTime('-5 minutes');
        $raw = (string)$user->getPassword() . $date->format('Ymd-H-i-s') . '0';
        $token = $date->format('Ymd-H-i-s') . '0' . $services->get(PasswordHasherFactory::class)->getPasswordHasher('cookie')->hash($raw);
        unset($_COOKIE[LoginCookie::NAME]);
        $_COOKIE = ['name' => $tag, 'token' => $token];
        $services->get(CurrentRequest::class)->replace(new Request([], [], [], $_COOKIE));
        $browser = $this->browser();
        $browser->connectUser();

        return $browser;
    }

    public function testAnOldCookiePairStillSignsALocalAccountInWhileLocalIsOn(): void
    {
        $this->installProviders(['local' => ['type' => 'local']]);
        $this->assertSame('SessionLocal', $this->connectFromOldCookies('SessionLocal')->signedInName());
    }

    public function testAnOldCookiePairDoesNotSignALocalAccountInWhenLocalIsOff(): void
    {
        $this->installProviders(['acme' => ['type' => 'fakeredirect'], 'local' => ['type' => 'local', 'enabled' => false]]);
        $this->assertSame('', $this->connectFromOldCookies('SessionLocal')->signedInName());
    }
}
