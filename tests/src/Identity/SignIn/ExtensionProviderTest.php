<?php

namespace YesWiki\Test\Identity\SignIn;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\HelloWorld\Service\HelloPasswordType;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\LoginCookie;
use YesWiki\Identity\Service\PasswordHasherFactory;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\SignIn\AttemptLimiter;
use YesWiki\Identity\Service\SignIn\HandshakeCookie;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\Service\SignIn\SignInFlow;
use YesWiki\Identity\Service\SignIn\SignInJournal;
use YesWiki\Identity\Service\SignIn\SignInService;
use YesWiki\Identity\Service\SignIn\Type\LocalType;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\PasswordProvider;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The helloworld sample adds a sign-in provider type with one service, and core gives it everything else. */
class ExtensionProviderTest extends YesWikiTestCase
{
    private const EMAILS = ['extlogin@example.invalid', 'exttaken@example.invalid'];
    private const TAGS = ['ExtLogin', 'ExtTaken'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        $db = $this->db();
        $action = $db->quoteIdentifier('action');
        $target = $db->quoteIdentifier('target');
        $db->query("DELETE FROM {$this->journal()} WHERE ({$action} LIKE 'login.%' AND ({$action} LIKE '%.hello' OR {$target} IN ('extlogin', 'exttaken', 'a')))");
        unset($_COOKIE[LoginCookie::NAME]);
        parent::tearDown();
    }

    private function db(): DbService
    {
        return self::getWiki()->services->get(DbService::class);
    }

    private function journal(): string
    {
        return $this->db()->quoteIdentifier(self::getWiki()->services->get(JournalSchema::class)->table());
    }

    private function cleanUp(): void
    {
        $services = self::getWiki()->services;
        $users = $services->get(UserManager::class);
        foreach (self::EMAILS as $email) {
            if ($user = $users->getOneByEmail($email)) {
                $users->delete($user);
            }
        }
        foreach (self::TAGS as $tag) {
            if ($user = $users->getOneByName($tag)) {
                $users->delete($user);
            }
            $services->get(PageManager::class)->deleteOrphaned($tag);
        }
    }

    /**
     * What a wiki with helloworld switched on holds, found the way the container finds it: a process of its own, started with a configuration that switches the extension on, so the developer's own file is never touched.
     *
     * @return array<string, mixed>
     */
    private function containerWithHelloworld(): array
    {
        $root = realpath(__DIR__ . '/../../../..');
        $config = sys_get_temp_dir() . '/ext-provider-' . bin2hex(random_bytes(4)) . '.php';
        $script = $config . '.run.php';
        file_put_contents($config, "<?php\n\nrequire " . var_export($root . '/yeswiki.config.php', true) . ";\n\$yeswikiConfig['active_extensions'] = array_values(array_unique(array_merge(\$yeswikiConfig['active_extensions'] ?? [], ['helloworld'])));\n");
        file_put_contents($script, "<?php\nchdir(" . var_export($root, true) . ");\nrequire 'src/YesWikiLoader.php';\n\$wiki = \\YesWiki\\Core\\YesWikiLoader::getWiki(true);\n"
            . "\$registry = \$wiki->services->get(" . var_export(ProviderRegistry::class, true) . ");\n"
            . "echo json_encode(['types' => array_map(fn (\$t) => \$t::class, \$registry->types())]);\n");
        try {
            $output = [];
            exec('YESWIKI_CONFIG_FILE=' . escapeshellarg($config) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>/dev/null', $output);
            $decoded = json_decode(implode("\n", $output), true);
            $this->assertIsArray($decoded, 'the wiki started with helloworld on: ' . implode("\n", $output));

            return $decoded;
        } finally {
            @unlink($config);
            @unlink($script);
        }
    }

    /** @param array<string, array<string, mixed>> $section */
    private function registry(array $section): ProviderRegistry
    {
        $values = ['sso_providers' => $section];
        $config = new RuntimeConfig();
        $config->bind($values);
        $types = [self::getWiki()->services->get(LocalType::class), new HelloPasswordType()];

        return new ProviderRegistry($types, $config);
    }

    /** @param array<string, array<string, mixed>> $section */
    private function flowFor(array $section): SignInFlow
    {
        $services = self::getWiki()->services;
        $registry = $this->registry($section);
        $journal = new SignInJournal($services->get(Journal::class), $services->get(AuthenticationService::class), $services->get(AttemptLimiter::class), $registry);
        $browser = new class($services->get(AccountActivationService::class), $services->get(HibernationService::class), $services->get(ParameterBagInterface::class), $services->get(PasswordHasherFactory::class), $services->get(UserManager::class), $services) extends AuthenticationService {
            protected function isCli(): bool
            {
                return false;
            }
        };
        $browser->setServices($services);

        return new SignInFlow($registry, $services->get(SignInService::class), $journal, $services->get(AttemptLimiter::class), $services->get(HandshakeCookie::class), $browser, $services->get(UrlFormatter::class), $services->get(AclService::class));
    }

    /** @return array<string, array<string, mixed>> */
    private function hello(string $password, bool $matchByEmail = false): array
    {
        return ['hello' => ['type' => 'hello-password', 'connection' => ['password' => $password], 'match_by_email' => $matchByEmail]];
    }

    public function testTheTypeIsFoundWithNoOtherRegistration(): void
    {
        $found = $this->containerWithHelloworld();

        $this->assertArrayHasKey('hello-password', $found['types']);
        $this->assertSame(HelloPasswordType::class, $found['types']['hello-password']);
    }

    public function testTheTypeDescribesItsPasswordAsASecretWithNoDefault(): void
    {
        $type = new HelloPasswordType();

        $this->assertSame('hello-password', $type->name());
        $settings = $type->connectionSettings();
        $this->assertCount(1, $settings);
        $this->assertSame('password', $settings[0]->name());
        $this->assertSame('secret', $settings[0]->type());
        $this->assertArrayNotHasKey('default', $settings[0]->toArray());
    }

    public function testWithoutAConfiguredPasswordNothingSignsIn(): void
    {
        $provider = $this->registry($this->hello(''))->get('hello');
        $this->assertInstanceOf(PasswordProvider::class, $provider);

        foreach ([['a', ''], ['a', 'x']] as [$login, $password]) {
            try {
                $provider->authenticate($login, $password);
                $this->fail('signed in with an unconfigured password');
            } catch (ProviderFailure $failure) {
                $this->assertSame(FailureReason::WrongCredentials, $failure->reason);
            }
        }
        $absent = $this->registry(['hello' => ['type' => 'hello-password']])->get('hello');
        $this->assertInstanceOf(PasswordProvider::class, $absent);
        $this->expectException(ProviderFailure::class);
        $absent->authenticate('a', '');
    }

    public function testAWrongOrEmptyPasswordIsRefusedAndTheRightOneGivesUidAndMail(): void
    {
        $provider = $this->registry($this->hello('open-sesame'))->get('hello');
        $this->assertInstanceOf(PasswordProvider::class, $provider);

        foreach ([['extlogin', ''], ['extlogin', 'nope'], ['', 'open-sesame']] as [$login, $password]) {
            try {
                $provider->authenticate($login, $password);
                $this->fail('signed in with ' . $login . '/' . $password);
            } catch (ProviderFailure $failure) {
                $this->assertSame(FailureReason::WrongCredentials, $failure->reason);
            }
        }
        $identity = $provider->authenticate('extlogin', 'open-sesame');
        $this->assertSame(['uid' => 'extlogin', 'mail' => 'extlogin@example.invalid'], $identity->attributes);
    }

    public function testItSignsInThroughTheCoreFlow(): void
    {
        $flow = $this->flowFor($this->hello('open-sesame'));

        $outcome = $flow->password('hello', 'extlogin', 'open-sesame', false);

        $this->assertSame('signed_in', $outcome->kind);
        $users = self::getWiki()->services->get(UserManager::class);
        $user = $users->getOneByEmail('extlogin@example.invalid');
        $this->assertNotNull($user, 'core created the account');
        $this->assertSame('hello', $user->provider());
    }

    public function testACoreRuleStillApplies(): void
    {
        self::getWiki()->services->get(UserManager::class)->create('ExtTaken', 'extlogin@example.invalid', 'a-long-enough-password');
        $flow = $this->flowFor($this->hello('open-sesame', false));

        $outcome = $flow->password('hello', 'extlogin', 'open-sesame', false);

        $this->assertSame('refused', $outcome->kind);
        $this->assertSame(FailureReason::EmailConflict, $outcome->reason);
    }

    public function testASwitchedOffExtensionLeavesItsProviderUnavailable(): void
    {
        $values = ['sso_providers' => $this->hello('open-sesame')];
        $config = new RuntimeConfig();
        $config->bind($values);
        $registry = new ProviderRegistry([self::getWiki()->services->get(LocalType::class)], $config);

        $this->assertArrayHasKey('hello', $registry->configs(), 'the configuration is kept');
        $this->assertSame('open-sesame', $registry->config('hello')?->connection('password'));
        $this->assertSame([], $registry->enabled(), 'not offered on the sign-in page');
        $this->assertNull($registry->get('hello'));
        $this->assertNotEmpty(array_filter($registry->problems(), fn (string $p): bool => str_contains($p, 'hello-password') && str_contains($p, 'not installed')));
    }
}
