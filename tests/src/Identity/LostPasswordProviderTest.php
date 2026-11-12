<?php

namespace YesWiki\Test\Identity;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Action\LostPasswordAction;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Service\PasswordHasherFactory;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Only a local account has a password to recover, and only while local accounts can sign in. */
class LostPasswordProviderTest extends YesWikiTestCase
{
    private const LOCAL = 'LostPwLocal';
    private const LINKED = 'LostPwLinked';

    /** @var mixed */
    private $sectionWas;
    private Request $requestWas;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->requestWas = $services->get(CurrentRequest::class)->get();
        $this->sectionWas = $services->get(RuntimeConfig::class)['sso_providers'];
        $this->cleanUp();
        $users = $services->get(UserManager::class);
        $users->create(self::LOCAL, 'lostpw-local@example.com', 'a-long-enough-password');
        $linked = $users->create(self::LINKED, 'lostpw-linked@example.com', 'a-long-enough-password');
        $this->assertNotNull($linked);
        $services->get(AccountLinks::class)->link($linked, 'acme', 'lostpw-subject');
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        $config = $services->get(RuntimeConfig::class);
        if ($this->sectionWas === null) {
            unset($config['sso_providers']);
        } else {
            $config['sso_providers'] = $this->sectionWas;
        }
        $services->get(CurrentRequest::class)->replace($this->requestWas);
        $services->get(AuthenticationService::class)->logout();
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $services = self::getWiki()->services;
        foreach ([self::LOCAL, self::LINKED] as $tag) {
            if ($user = $services->get(UserManager::class)->getOneByName($tag)) {
                $services->get(UserManager::class)->delete($user);
            }
            $services->get(TripleStore::class)->delete($tag, UserManager::KEY_VOCABULARY, null, '', '');
            $services->get(PageManager::class)->deleteOrphaned($tag);
        }
    }

    private function localOff(): void
    {
        self::getWiki()->services->get(RuntimeConfig::class)['sso_providers'] = ['local' => ['type' => 'local', 'enabled' => false]];
    }

    private function hasKey(string $tag): bool
    {
        return self::getWiki()->services->get(TripleStore::class)->getOne($tag, UserManager::KEY_VOCABULARY, '', '') !== null;
    }

    private function account(string $name): User
    {
        $user = self::getWiki()->services->get(UserManager::class)->getOneByName($name);
        $this->assertNotNull($user, "$name exists");

        return $user;
    }

    /** @param array<string, string> $post */
    private function submit(int $subStep, array $post): void
    {
        $services = self::getWiki()->services;
        $services->get(CurrentRequest::class)->replace(Request::create('/?MotDePassePerdu', 'POST', ['subStep' => (string)$subStep] + $post));
        $action = new LostPasswordAction();
        $action->setServices($services);
        $reflection = new \ReflectionClass($action);
        foreach ([
            'authenticationService' => AuthenticationService::class,
            'hibernationService' => HibernationService::class,
            'tripleStore' => TripleStore::class,
            'userManager' => UserManager::class,
        ] as $property => $service) {
            $reflection->getProperty($property)->setValue($action, $services->get($service));
        }
        // filter_input() reads the SAPI's input, which a CLI test run has none of: read the request that was posted instead.
        $posted = $services->get(CurrentRequest::class)->get()->request;
        $reflection->getProperty('inputFilter')->setValue($action, new class($posted) extends InputFilter {
            public function __construct(private ParameterBag $posted)
            {
            }

            public function filterInput(int $type, string $varName, int $filter = FILTER_DEFAULT, bool $emulateFilterSanitizeString = false, string $format = '', $options = 0)
            {
                return $this->posted->get($varName);
            }
        });
        // the bot guard stands aside for an administrator; what is tested here is not the guard.
        self::actAs($subStep === 1 ? 'WikiAdmin' : null);
        try {
            $reflection->getMethod('manageSubStep')->invoke($action, $subStep);
        } finally {
            self::actAs(null);
        }
    }

    private function plantKey(string $tag): string
    {
        $services = self::getWiki()->services;
        $user = $services->get(UserManager::class)->getOneByName($tag);
        $this->assertNotNull($user);
        $key = $services->get(PasswordHasherFactory::class)->getPasswordHasher($user)->hash('probe-key');
        $services->get(TripleStore::class)->create($tag, UserManager::KEY_VOCABULARY, $key . UserManager::KEY_VALUE_SEPARATOR . time(), '', '');

        return $key;
    }

    public function testALocalAccountGetsARecoveryKey(): void
    {
        $this->submit(1, ['email' => 'lostpw-local@example.com']);
        $this->assertTrue($this->hasKey(self::LOCAL), 'the control: the send step does work');
    }

    public function testALinkedAccountGetsNoRecoveryKey(): void
    {
        $this->submit(1, ['email' => 'lostpw-linked@example.com']);
        $this->assertFalse($this->hasKey(self::LINKED));
    }

    public function testNobodyGetsARecoveryKeyWhenLocalIsOff(): void
    {
        $this->localOff();
        $this->submit(1, ['email' => 'lostpw-local@example.com']);
        $this->assertFalse($this->hasKey(self::LOCAL));
    }

    public function testALinkedAccountCannotSetAPasswordNorSignIn(): void
    {
        $key = $this->plantKey(self::LINKED);
        $this->submit(2, ['userID' => self::LINKED, 'key' => $key, 'pw0' => 'Aa1!brandNewPassword', 'pw1' => 'Aa1!brandNewPassword']);

        $services = self::getWiki()->services;
        $this->assertSame('', $services->get(AuthenticationService::class)->signedInName());
        $this->assertSame('', (string)$this->account(self::LINKED)->getPassword());
    }

    public function testNothingIsResetWhenLocalIsOff(): void
    {
        $key = $this->plantKey(self::LOCAL);
        $this->localOff();
        $services = self::getWiki()->services;
        $before = $this->account(self::LOCAL)->getPassword();

        $this->submit(2, ['userID' => self::LOCAL, 'key' => $key, 'pw0' => 'Aa1!brandNewPassword', 'pw1' => 'Aa1!brandNewPassword']);

        $this->assertSame('', $services->get(AuthenticationService::class)->signedInName());
        $this->assertSame($before, $this->account(self::LOCAL)->getPassword());
    }
}
