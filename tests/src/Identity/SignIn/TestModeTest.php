<?php

namespace YesWiki\Test\Identity\SignIn;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Controller\AdminSignInController;
use YesWiki\Identity\Controller\RendersSignInOutcome;
use YesWiki\Identity\Service\AccountActivationService;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\LoginCookie;
use YesWiki\Identity\Service\PasswordHasherFactory;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\SignIn\AccountNamer;
use YesWiki\Identity\Service\SignIn\AttemptLimiter;
use YesWiki\Identity\Service\SignIn\GroupSync;
use YesWiki\Identity\Service\SignIn\HandshakeCookie;
use YesWiki\Identity\Service\SignIn\ProfileSync;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\Service\SignIn\SignInFlow;
use YesWiki\Identity\Service\SignIn\SignInJournal;
use YesWiki\Identity\Service\SignIn\SignInService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\SignIn\AppliedSignIn;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Identity\SignIn\Contract\HandshakeMode;
use YesWiki\Identity\SignIn\Contract\IdentityProvider;
use YesWiki\Identity\SignIn\Contract\PasswordProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Identity\SignIn\Contract\RedirectProvider;
use YesWiki\Identity\SignIn\PlanAction;
use YesWiki\Identity\SignIn\SignInOutcome;
use YesWiki\Identity\SignIn\SignInPlan;
use YesWiki\Identity\SignIn\SignInTrace;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Lock\Locks;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once __DIR__ . '/FakeProviderTypes.php';

/** A redirect provider whose answer each test decides. */
final class TestModeFakeRedirect implements RedirectProvider
{
    public ?ProviderConfig $config = null;

    /** @var array<string, mixed> */
    public array $attributes = ['sub' => 'testmode-new', 'email' => 'testmode-new@example.com'];

    public ?\Throwable $failure = null;

    public ?\Throwable $failureAtBegin = null;

    public int $completed = 0;

    public function id(): string
    {
        return (string)$this->config?->id;
    }

    public function label(): string
    {
        return (string)$this->config?->label;
    }

    public function type(): string
    {
        return (string)$this->config?->type;
    }

    public function begin(Handshake $handshake): Response
    {
        if ($this->failureAtBegin !== null) {
            throw $this->failureAtBegin;
        }

        return new RedirectResponse('https://idp.example/authorize');
    }

    public function complete(Request $request, Handshake $handshake): ProviderIdentity
    {
        $this->completed++;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new ProviderIdentity($this->attributes);
    }
}

/** A directory that accepts the password "good". */
final class TestModeFakePassword implements PasswordProvider
{
    public ?ProviderConfig $config = null;

    public int $calls = 0;

    public function id(): string
    {
        return (string)$this->config?->id;
    }

    public function label(): string
    {
        return (string)$this->config?->label;
    }

    public function type(): string
    {
        return (string)$this->config?->type;
    }

    public function authenticate(string $login, #[\SensitiveParameter] string $password): ProviderIdentity
    {
        $this->calls++;
        if ($password !== 'good') {
            throw new ProviderFailure(FailureReason::WrongCredentials, 'bad password');
        }

        return new ProviderIdentity(['sub' => 'testmode-pw', 'email' => 'testmode-pw@example.com', 'local_account' => self::LOCAL]);
    }

    public const LOCAL = 'TestModeLocal';
}

/**
 * An administrator tries a provider out (spec 8.3, "Test"): the real protocol runs, only plan() is called,
 * and nobody is signed in, nothing is written, nothing is counted.
 */
class TestModeTest extends YesWikiTestCase
{
    use FakeProviderTypes;

    private const ADMIN = 'WikiAdmin';
    private const PLAIN = 'TestModePlain';
    private const OTHER_ADMIN = 'TestModeOtherAdmin';
    private const LINKED = 'TestModeLinked';
    private const TAKEN = 'TestModeTaken';
    private const SECRET = 'testmode-client-s3cret';
    private const EMAILS = ['testmode-new@example.com', 'testmode-pw@example.com', 'testmode-plain@example.com', 'testmode-other@example.com', 'testmode-linked@example.com', 'testmode-taken@example.com', 'testmode-marie@example.com'];
    private const LOGINS = ['locked', 'testmode'];

    private SignInFlow $flow;
    private TestModeFakeRedirect $fake;
    private TestModeFakePassword $fakePw;
    private HandshakeCookie $cookie;
    private Request $requestWas;

    /** @var SignInService&object{applied: int} */
    private SignInService $service;

    /** @var AuthenticationService&object{logins: int} */
    private AuthenticationService $browser;

    /** @var array<string, mixed> */
    private array $cookiesWere;

    private int $journalFloor = 0;

    /** @var array<string, array<string, object>>|null the container's service tables before a test put fakes in them */
    private ?array $servicesWere = null;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->requestWas = $services->get(CurrentRequest::class)->get();
        $this->cookiesWere = $_COOKIE;
        unset($_COOKIE[HandshakeCookie::NAME], $_COOKIE[LoginCookie::NAME]);
        $this->cleanUp();
        $users = $services->get(UserManager::class);
        $users->create(self::PLAIN, 'testmode-plain@example.com', 'a-long-enough-password');
        $users->create(self::OTHER_ADMIN, 'testmode-other@example.com', 'a-long-enough-password');
        $services->get(GroupManager::class)->addMembers('admins', [self::OTHER_ADMIN]);
        $services->get(AclService::class)->startNewRequest();
        $this->journalFloor = $this->latestJournalRow();

        $this->fake = new TestModeFakeRedirect();
        $this->fakePw = new TestModeFakePassword();
        $this->cookie = $services->get(HandshakeCookie::class);
        $this->service = $this->spyService();
        $this->browser = $this->browser();
        $this->browser->actAs(self::ADMIN);
        $this->flow = $this->flowFor([
            'fake' => ['type' => 'fake-redirect', 'label' => 'Fake SSO', 'connection' => ['client_secret' => self::SECRET]],
            'off' => ['type' => 'fake-redirect', 'label' => 'Switched off', 'enabled' => false],
            'fakepw' => ['type' => 'fake-password', 'label' => 'Fake directory'],
            'local' => ['type' => 'local'],
            'orphan' => ['type' => 'not-installed', 'label' => 'Gone'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->restoreProviders();
        $this->restoreServices();
        self::actAs(null);
        $services = self::getWiki()->services;
        $services->get(GroupManager::class)->removeMembers('admins', [self::OTHER_ADMIN]);
        $services->get(AclService::class)->startNewRequest();
        $this->cleanUp();
        $this->db()->query("DELETE FROM {$this->journal()} WHERE {$this->db()->quoteIdentifier('id')} > {$this->journalFloor} AND {$this->db()->quoteIdentifier('action')} IN ('login.failed', 'login.throttled')");
        $services->get(CurrentRequest::class)->replace($this->requestWas);
        $_COOKIE = $this->cookiesWere;
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
        $tags = [self::PLAIN, self::OTHER_ADMIN, self::LINKED, self::TAKEN, TestModeFakePassword::LOCAL];
        foreach (self::EMAILS as $email) {
            if ($user = $users->getOneByEmail($email)) {
                $tags[] = $user->getName();
            }
        }
        foreach (array_unique($tags) as $tag) {
            if ($user = $users->getOneByName($tag)) {
                $users->delete($user);
            }
            $services->get(PageManager::class)->deleteOrphaned($tag);
        }
        $action = $this->db()->quoteIdentifier('action');
        $target = $this->db()->quoteIdentifier('target');
        $logins = implode(',', array_map(fn (string $login): string => "'" . $login . "'", self::LOGINS));
        $this->db()->query("DELETE FROM {$this->journal()} WHERE {$action} LIKE 'login.%.fake' OR {$action} LIKE 'login.%.fakepw' OR {$action} LIKE 'login.%.off' OR {$action} LIKE 'login.%.orphan' OR {$action} LIKE 'login.%.local'"
            . " OR ({$action} IN ('login.failed', 'login.throttled') AND ({$target} IN ({$logins}) OR {$target} LIKE 'testmode-%'))");
    }

    /** @param array<string, array<string, mixed>> $section */
    private function flowFor(array $section): SignInFlow
    {
        $services = self::getWiki()->services;
        $values = ['sso_providers' => $section];
        $config = new RuntimeConfig();
        $config->bind($values);
        $registry = new ProviderRegistry([
            $this->spiedType('fake-redirect', $this->fake),
            $this->spiedType('fake-password', $this->fakePw),
            $this->spiedType('local', $this->fakePw),
        ], $config);
        $journal = new SignInJournal($services->get(Journal::class), $services->get(AuthenticationService::class), $services->get(AttemptLimiter::class), $registry);

        return new SignInFlow($registry, $this->service, $journal, $services->get(AttemptLimiter::class), $services->get(HandshakeCookie::class), $this->browser, $services->get(UrlFormatter::class), $services->get(AclService::class));
    }

    private function spiedType(string $name, TestModeFakeRedirect|TestModeFakePassword $provider): ProviderType
    {
        return new class($name, $provider) implements ProviderType {
            public function __construct(private string $name, private TestModeFakeRedirect|TestModeFakePassword $provider)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function label(): string
            {
                return $this->name;
            }

            public function connectionSettings(): array
            {
                return [];
            }

            public function defaultAttributes(): array
            {
                return ['id' => 'sub', 'email' => 'email', 'first_name' => 'given_name', 'last_name' => 'family_name', 'groups' => 'groups'];
            }

            public function create(ProviderConfig $config): IdentityProvider
            {
                $this->provider->config = $config;

                return $this->provider;
            }
        };
    }

    /** The real service, counting the plans it is asked to carry out. */
    private function spyService(): SignInService
    {
        $services = self::getWiki()->services;

        return new class($services->get(AccountLinks::class), $services->get(UserManager::class), $services->get(GroupManager::class), $services->get(AccountNamer::class), $services->get(ProfileSync::class), $services->get(GroupSync::class), $services->get(Locks::class), $services->get(HibernationService::class)) extends SignInService {
            public int $applied = 0;

            public function apply(SignInPlan $plan): AppliedSignIn
            {
                $this->applied++;

                return parent::apply($plan);
            }
        };
    }

    /** The service as a web request has it: the one that really sends yw-auth, counting the sign-ins it is asked for. */
    private function browser(): AuthenticationService
    {
        $services = self::getWiki()->services;
        $browser = new class($services->get(AccountActivationService::class), $services->get(HibernationService::class), $services->get(ParameterBagInterface::class), $services->get(PasswordHasherFactory::class), $services->get(UserManager::class), $services) extends AuthenticationService {
            public int $logins = 0;

            protected function isCli(): bool
            {
                return false;
            }

            public function login($user, $remember = 0): void
            {
                $this->logins++;
                parent::login($user, $remember);
            }
        };
        $browser->setServices($services);

        return $browser;
    }

    private function issuedCookie(string $name): string
    {
        return (string)($_COOKIE[$name] ?? '');
    }

    /** The browser's return from the provider, after a test the signed-in administrator started. */
    private function testCallback(?array $attributes = null, string $provider = 'fake'): Request
    {
        if ($attributes !== null) {
            $this->fake->attributes = $attributes;
        }
        $this->assertInstanceOf(Response::class, $this->flow->begin($provider, '', HandshakeMode::Test));
        $this->assertNotSame('', $this->issuedCookie(HandshakeCookie::NAME), 'a handshake to come back with');

        return new Request(query: ['code' => 'from-the-provider'], cookies: [HandshakeCookie::NAME => $this->issuedCookie(HandshakeCookie::NAME)]);
    }

    private function latestJournalRow(): int
    {
        return (int)$this->db()->scalar("SELECT COALESCE(MAX({$this->db()->quoteIdentifier('id')}), 0) FROM {$this->journal()}", 0);
    }

    /** Every Journal row written after $since, on any channel. */
    private function journalSince(int $since): int
    {
        $id = $this->db()->quoteIdentifier('id');

        return (int)$this->db()->scalar("SELECT COUNT(*) FROM {$this->journal()} WHERE {$id} > {$since}", 0);
    }

    private function countJournal(string $where): int
    {
        $id = $this->db()->quoteIdentifier('id');

        return (int)$this->db()->scalar("SELECT COUNT(*) FROM {$this->journal()} WHERE ({$where}) AND {$id} > {$this->journalFloor}", 0);
    }

    /** Every page revision and every triple: what any account, link or group change would add to. */
    private function countUserRevisions(): int
    {
        return (int)$this->db()->scalar("SELECT COUNT(*) FROM {$this->db()->prefixTable('pages')}", 0)
            + (int)$this->db()->scalar("SELECT COUNT(*) FROM {$this->db()->prefixTable('triples')}", 0);
    }

    private function assertNothingHappened(string $message = ''): void
    {
        $this->assertSame(0, $this->service->applied, 'apply() is never called' . $message);
        $this->assertSame(0, $this->browser->logins, 'nobody is signed in' . $message);
        $this->assertSame('', $this->issuedCookie(LoginCookie::NAME), 'no yw-auth is issued' . $message);
        $this->assertSame(self::ADMIN, $this->browser->signedInName(), 'the administrator is still who is signed in' . $message);
    }

    /** @return list<string> the steps of a tested outcome, as `step:state` */
    private static function steps(SignInOutcome $outcome): array
    {
        return array_map(fn (array $step): string => $step['step'] . ':' . $step['state'], $outcome->steps);
    }

    private function renderer(): object
    {
        $renderer = new class extends YesWikiController {
            use RendersSignInOutcome;

            public function show(Response|SignInOutcome $outcome): Response
            {
                return $this->renderSignInOutcome($outcome);
            }
        };
        $renderer->setServices(self::getWiki()->services);

        return $renderer;
    }

    private function renderTested(SignInOutcome $outcome): string
    {
        $this->assertSame(SignInOutcome::TESTED, $outcome->kind);
        $response = $this->renderer()->show($outcome);
        $this->assertSame(200, $response->getStatusCode());

        return (string)$response->getContent();
    }

    /** A test of someone the wiki has never seen: a real sign-in would create their account. */
    private function createPlan(): SignInOutcome
    {
        return $this->flow->callback($this->testCallback(['sub' => 'testmode-marie', 'email' => 'testmode-marie@example.com', 'given_name' => 'Marie', 'family_name' => 'Dupont']), 'fake');
    }

    public function testATestSignsNobodyInAndChangesNothing(): void
    {
        $before = $this->countUserRevisions();

        $outcome = $this->flow->callback($this->testCallback(attributes: ['sub' => 's', 'email' => 'testmode-new@example.com']), 'fake');

        $this->assertSame('tested', $outcome->kind);
        $this->assertSame(PlanAction::Create, $outcome->plan?->action, 'a real sign-in would have created the account');
        $this->assertSame($before, $this->countUserRevisions());
        $this->assertNull(self::getWiki()->services->get(UserManager::class)->getOneByEmail('testmode-new@example.com'));
        $this->assertNothingHappened();
    }

    public function testATestWritesNothingToTheJournal(): void
    {
        // a linked account whose new email another account holds: a real sign-in would write a warning.
        $services = self::getWiki()->services;
        $users = $services->get(UserManager::class);
        $linked = $users->create(self::LINKED, 'testmode-linked@example.com', 'a-long-enough-password');
        $users->create(self::TAKEN, 'testmode-taken@example.com', 'a-long-enough-password');
        $this->assertNotNull($linked);
        $services->get(AccountLinks::class)->link($linked, 'fake', 'testmode-linked-subject');
        $request = $this->testCallback(['sub' => 'testmode-linked-subject', 'email' => 'testmode-taken@example.com']);
        $since = $this->latestJournalRow();

        $outcome = $this->flow->callback($request, 'fake');

        $this->assertSame('tested', $outcome->kind);
        $this->assertSame(PlanAction::UseLinked, $outcome->plan?->action);
        $this->assertContains('email_collision', array_column($outcome->plan->warnings, 'kind'), 'the warning is in the plan, for the screen');
        $this->assertSame(0, $this->journalSince($since), 'no audit, diagnostic or warning row');
        $this->assertSame(0, $this->countJournal("action LIKE 'login.%'"));
        $this->assertNothingHappened();
    }

    public function testAFailedTestShowsTheErrorAndWritesNothing(): void
    {
        $request = $this->testCallback();
        $this->fake->failure = new ProviderFailure(FailureReason::ProviderRejected, 'invalid_client: ' . self::SECRET . ' was refused', null, ['sub' => 'someone']);
        $since = $this->latestJournalRow();

        $outcome = $this->flow->callback($request, 'fake');

        $this->assertSame('tested', $outcome->kind);
        $this->assertStringContainsString('invalid_client', $outcome->error);
        $this->assertStringNotContainsString(self::SECRET, $outcome->error, 'the raw error is scrubbed of the secrets');
        $this->assertSame(FailureReason::ProviderRejected, $outcome->plan?->refusal);
        $this->assertSame(PlanAction::Refuse, $outcome->plan->action, 'a real sign-in would be refused');
        $this->assertArrayHasKey('sub', $outcome->plan->attributes, 'what came back is shown');
        $this->assertSame(['sent_to_provider:done', 'provider_answered:failed', 'attributes_read:not_reached', 'account_matched:not_reached', 'groups:not_reached'], self::steps($outcome));
        $this->assertSame(0, $this->journalSince($since));
        $this->assertNothingHappened();
    }

    public function testAnUnexpectedExceptionIsATestThatFailed(): void
    {
        $request = $this->testCallback();
        $this->fake->failure = new \LogicException('boom');
        $since = $this->latestJournalRow();

        $outcome = $this->flow->callback($request, 'fake');

        $this->assertSame('tested', $outcome->kind);
        $this->assertSame(FailureReason::InternalError, $outcome->plan?->refusal);
        $this->assertStringContainsString('boom', $outcome->error);
        $this->assertSame(0, $this->journalSince($since));
    }

    public function testAFailureWhileLeavingIsATestThatFailed(): void
    {
        $this->fake->failureAtBegin = new ProviderFailure(FailureReason::ProviderUnreachable, 'discovery timed out');

        $outcome = $this->flow->begin('fake', '', HandshakeMode::Test);

        $this->assertInstanceOf(SignInOutcome::class, $outcome);
        $this->assertSame('tested', $outcome->kind);
        $this->assertSame('discovery timed out', $outcome->error);
        $this->assertSame(['sent_to_provider:failed', 'provider_answered:not_reached', 'attributes_read:not_reached', 'account_matched:not_reached', 'groups:not_reached'], self::steps($outcome));
        $this->assertSame('', $this->issuedCookie(HandshakeCookie::NAME));
        $this->assertSame(0, $this->journalSince($this->journalFloor));
    }

    public function testTheStepsOfATestThatWentThrough(): void
    {
        $outcome = $this->flow->callback($this->testCallback(), 'fake');

        $this->assertSame(['sent_to_provider:done', 'provider_answered:done', 'attributes_read:done', 'account_matched:done', 'groups:done'], self::steps($outcome));
        $this->assertSame('', $outcome->error);
    }

    public function testARefusedPlanStopsTheStepsWhereItWasRefused(): void
    {
        $this->flow = $this->flowFor(['fake' => ['type' => 'fake-redirect', 'create_accounts' => false]]);
        $missing = $this->flow->callback($this->testCallback(['sub' => 'testmode-nomail']), 'fake');
        $noAccount = $this->flow->callback($this->testCallback(['sub' => 'testmode-new', 'email' => 'testmode-new@example.com']), 'fake');

        $this->assertSame(FailureReason::MissingAttribute, $missing->plan?->refusal);
        $this->assertSame(['sent_to_provider:done', 'provider_answered:done', 'attributes_read:failed', 'account_matched:not_reached', 'groups:not_reached'], self::steps($missing));
        $this->assertStringContainsString('"email"', $missing->error, 'the plan says which attribute it looked for');
        $this->assertSame(FailureReason::NoAccount, $noAccount->plan?->refusal);
        $this->assertSame(['sent_to_provider:done', 'provider_answered:done', 'attributes_read:done', 'account_matched:failed', 'groups:not_reached'], self::steps($noAccount));
        $this->assertSame(0, $this->journalSince($this->journalFloor));
    }

    public function testATestRequiresAnAdmin(): void
    {
        $this->browser->actAs(self::PLAIN);

        $outcome = $this->flow->begin('fake', '', HandshakeMode::Test);

        $this->assertInstanceOf(SignInOutcome::class, $outcome);
        $this->assertSame('refused', $outcome->kind);
        $this->assertSame(FailureReason::InvalidResponse, $outcome->reason);
        $this->assertSame('', $this->issuedCookie(HandshakeCookie::NAME), 'no handshake to come back with');
        $this->assertSame(0, $this->journalSince($this->journalFloor));
    }

    public function testATestRequiresSomeoneSignedIn(): void
    {
        $this->browser->actAs(null);

        $this->assertSame(FailureReason::InvalidResponse, $this->flow->begin('fake', '', HandshakeMode::Test)->reason);
    }

    /** @return array<string, array{?string}> */
    public static function notTheTester(): array
    {
        return [
            'nobody signed in' => [null],
            'a plain user' => [self::PLAIN],
            'another administrator' => [self::OTHER_ADMIN],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('notTheTester')]
    public function testTheCallbackOfATestBelongsToTheAdminWhoStartedIt(?string $whoComesBack): void
    {
        $request = $this->testCallback();
        $this->browser->actAs($whoComesBack);
        $since = $this->latestJournalRow();

        $outcome = $this->flow->callback($request, 'fake');

        $this->assertSame('refused', $outcome->kind);
        $this->assertSame(FailureReason::InvalidResponse, $outcome->reason);
        $this->assertSame(0, $this->fake->completed, 'the provider is never asked');
        $this->assertSame(0, $this->service->applied);
        $this->assertSame(0, $this->browser->logins);
        $this->assertSame('', $this->issuedCookie(LoginCookie::NAME));
        $this->assertSame(0, $this->journalSince($since), 'writes nothing');
    }

    public function testATestHandshakeBroughtToAnotherCallbackByAnyoneElseWritesNothing(): void
    {
        $request = $this->testCallback();
        $this->browser->actAs(self::PLAIN);
        $since = $this->latestJournalRow();

        $outcome = $this->flow->callback($request, 'off');

        $this->assertSame('refused', $outcome->kind);
        $this->assertSame(FailureReason::InvalidResponse, $outcome->reason);
        $this->assertSame(0, $this->journalSince($since));
    }

    public function testAnAdminWhoLostTheRightCannotFinishTheTest(): void
    {
        $this->browser->actAs(self::OTHER_ADMIN);
        $request = $this->testCallback();
        self::getWiki()->services->get(GroupManager::class)->removeMembers('admins', [self::OTHER_ADMIN]);
        self::getWiki()->services->get(AclService::class)->startNewRequest();

        $this->assertSame(FailureReason::InvalidResponse, $this->flow->callback($request, 'fake')->reason);
        $this->assertSame(0, $this->fake->completed);
    }

    public function testARealHandshakeStillSignsIn(): void
    {
        $this->assertInstanceOf(Response::class, $this->flow->begin('fake', ''));
        $request = new Request(cookies: [HandshakeCookie::NAME => $this->issuedCookie(HandshakeCookie::NAME)]);

        $this->assertSame('signed_in', $this->flow->callback($request, 'fake')->kind, 'only a test handshake is a test');
        $this->assertSame(1, $this->service->applied);
    }

    public function testATestWorksOnASwitchedOffProvider(): void
    {
        $outcome = $this->flow->callback($this->testCallback(provider: 'off'), 'off');

        $this->assertSame('tested', $outcome->kind);
        $this->assertSame('', $outcome->error);
        $this->assertSame(PlanAction::Create, $outcome->plan?->action);
        $this->assertSame('Switched off', $outcome->config?->label);
        $this->assertNothingHappened();
    }

    public function testATestDoesNotWorkOnAProviderWhoseTypeIsNotInstalled(): void
    {
        $outcome = $this->flow->begin('orphan', '', HandshakeMode::Test);

        $this->assertInstanceOf(SignInOutcome::class, $outcome);
        $this->assertSame('tested', $outcome->kind);
        $this->assertSame(FailureReason::ProviderDisabled, $outcome->plan?->refusal);
        $this->assertStringContainsString('not installed', $outcome->error);
        $this->assertSame('', $this->issuedCookie(HandshakeCookie::NAME));
        $this->assertSame(0, $this->journalSince($this->journalFloor));
    }

    public function testAnUnknownProviderIsRefusedAndNothingIsRecorded(): void
    {
        $outcome = $this->flow->begin('nobody-configured-this', '', HandshakeMode::Test);

        $this->assertInstanceOf(SignInOutcome::class, $outcome);
        $this->assertSame(FailureReason::ProviderDisabled, $outcome->reason);
        $this->assertSame(0, $this->journalSince($this->journalFloor));
    }

    public function testAPasswordProviderTestSignsNobodyIn(): void
    {
        $before = $this->countUserRevisions();

        $outcome = $this->flow->password('fakepw', 'testmode', 'good', true, HandshakeMode::Test);

        $this->assertSame('tested', $outcome->kind);
        $this->assertSame(PlanAction::Create, $outcome->plan?->action);
        $this->assertSame(['sent_to_provider:done', 'provider_answered:done', 'attributes_read:done', 'account_matched:done', 'groups:done'], self::steps($outcome));
        $this->assertSame($before, $this->countUserRevisions());
        $this->assertSame(0, $this->journalSince($this->journalFloor));
        $this->assertNothingHappened();
    }

    public function testAPasswordProviderTestDoesNotCountAgainstTheLimit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $outcome = $this->flow->password('fakepw', 'locked', 'bad', false, HandshakeMode::Test);
            $this->assertSame('tested', $outcome->kind, 'a wrong password is a test that failed, not a form error');
            $this->assertSame(FailureReason::WrongCredentials, $outcome->plan?->refusal);
            $this->assertSame('bad password', $outcome->error);
            $this->assertSame(['sent_to_provider:done', 'provider_answered:failed', 'attributes_read:not_reached', 'account_matched:not_reached', 'groups:not_reached'], self::steps($outcome));
        }

        $this->assertSame(6, $this->fakePw->calls);
        $this->assertFalse(self::getWiki()->services->get(AttemptLimiter::class)->isBlocked('locked'));
        $this->assertSame(0, $this->journalSince($this->journalFloor), 'no login.failed, no login.throttled');
    }

    public function testAPasswordProviderTestIsNotStoppedByTheLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->flow->password('fakepw', 'locked', 'bad', false);
        }
        $this->assertTrue(self::getWiki()->services->get(AttemptLimiter::class)->isBlocked('locked'));
        $since = $this->latestJournalRow();

        $outcome = $this->flow->password('fakepw', 'locked', 'good', false, HandshakeMode::Test);

        $this->assertSame('tested', $outcome->kind);
        $this->assertNull($outcome->plan?->refusal, 'the directory is asked: the limit is not consulted');
        $this->assertSame(6, $this->fakePw->calls);
        $this->assertSame(0, $this->journalSince($since), 'not even login.throttled');
    }

    public function testAPasswordProviderTestRequiresAnAdmin(): void
    {
        $this->browser->actAs(self::PLAIN);

        $outcome = $this->flow->password('fakepw', 'testmode', 'good', false, HandshakeMode::Test);

        $this->assertSame('refused', $outcome->kind);
        $this->assertSame(FailureReason::InvalidResponse, $outcome->reason);
        $this->assertSame(0, $this->fakePw->calls, 'the directory is never asked');
        $this->assertSame(0, $this->journalSince($this->journalFloor));
    }

    public function testAPasswordProviderTestWorksSwitchedOff(): void
    {
        $this->flow = $this->flowFor(['fakepw' => ['type' => 'fake-password', 'enabled' => false]]);

        $outcome = $this->flow->password('fakepw', 'testmode', 'good', false, HandshakeMode::Test);

        $this->assertSame('tested', $outcome->kind);
        $this->assertNull($outcome->plan?->refusal);
    }

    public function testTheWikisOwnAccountsAreNotTested(): void
    {
        // checking a local password can rewrite its hash: a test would change something.
        $outcome = $this->flow->password('local', 'testmode', 'good', false, HandshakeMode::Test);

        $this->assertSame('tested', $outcome->kind);
        $this->assertSame(FailureReason::ConfigurationInvalid, $outcome->plan?->refusal);
        $this->assertSame(0, $this->fakePw->calls);
    }

    public function testTheScreenStatesWhatARealSignInWouldDo(): void
    {
        $html = $this->renderTested($this->createPlan());

        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_TEST_BANNER'), ENT_QUOTES), $html);
        $this->assertStringContainsString('marie-dupont', $html);
        $this->assertStringContainsString('given_name', $html);
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_TEST_WOULD_CREATE', ['account' => 'marie-dupont', 'provider' => 'Fake SSO']), ENT_QUOTES), $html);
        $this->assertStringContainsString('Marie', $html, 'every attribute value is shown');
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_TEST_STEP_GROUPS'), ENT_QUOTES), $html);
        $this->assertStringContainsString('yw-dashboard', $html, 'inside the administration');
    }

    public function testTheScreenOfARefusalSaysWhyARealSignInWouldBeRefused(): void
    {
        $this->flow = $this->flowFor(['fake' => ['type' => 'fake-redirect', 'label' => 'Fake SSO', 'create_accounts' => false]]);

        $html = $this->renderTested($this->flow->callback($this->testCallback(), 'fake'));

        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_TEST_WOULD_REFUSE', ['reason' => _t('SIGN_IN_ADMIN_NO_ACCOUNT')]), ENT_QUOTES), $html);
        $this->assertStringContainsString('no account has this email and account creation is off', $html, 'the raw error');
    }

    public function testTheScreenShowsTheGroupMapping(): void
    {
        $this->flow = $this->flowFor(['fake' => ['type' => 'fake-redirect', 'group_mapping' => [['provider' => 'testmode-editors', 'wiki' => 'TestModeEditors']]]]);

        $html = $this->renderTested($this->flow->callback($this->testCallback(['sub' => 'testmode-new', 'email' => 'testmode-new@example.com', 'groups' => ['testmode-editors', 'testmode-unmapped']]), 'fake'));

        $this->assertStringContainsString('TestModeEditors', $html, 'the group a real sign-in would add');
        $this->assertStringContainsString('testmode-unmapped', $html, 'a group no row names');
    }

    public function testTheScreenEscapesWhatTheProviderSentAndShowsNoSecret(): void
    {
        $request = $this->testCallback(['sub' => '<script>alert("sub")</script>', 'email' => 'testmode-new@example.com', '<b>odd</b>' => 'x']);
        $this->fake->failure = new ProviderFailure(FailureReason::ProviderRejected, '<img src=x onerror=alert(1)> ' . self::SECRET, null, ['sub' => '<script>alert("sub")</script>']);

        $html = $this->renderTested($this->flow->callback($request, 'fake'));

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;sub&quot;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
    }

    public function testAnAttributeNameIsEscapedToo(): void
    {
        $html = $this->renderTested($this->flow->callback($this->testCallback(['sub' => 's', 'email' => 'testmode-new@example.com', '<b>odd</b>' => 'x']), 'fake'));

        $this->assertStringNotContainsString('<b>odd</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;odd&lt;/b&gt;', $html);
    }

    /**
     * The admin screen's own services, with these providers: the container refuses to replace a built service, so its table is written directly, as FakeProviderTypes does.
     *
     * @param array<string, array<string, mixed>> $section
     */
    private function controllerWith(array $section, bool $tokenAccepted = false): AdminSignInController
    {
        $services = self::getWiki()->services;
        $this->snapshotServices();
        $this->installProviders($section);
        $flow = new SignInFlow($services->get(ProviderRegistry::class), $this->service, $services->get(SignInJournal::class), $services->get(AttemptLimiter::class), $services->get(HandshakeCookie::class), $services->get(AuthenticationService::class), $services->get(UrlFormatter::class), $services->get(AclService::class));
        $this->swap(SignInFlow::class, $flow);
        if ($tokenAccepted) {
            // filter_input() reads the SAPI's input, which a CLI run has none of: the token is not what is tested here.
            $this->swap(CsrfTokenChecker::class, $this->createStub(CsrfTokenChecker::class));
        }
        self::actAs(self::ADMIN);

        return $services->get(AdminSignInController::class);
    }

    private function swap(string $id, object $service): void
    {
        $services = self::getWiki()->services;
        $table = new \ReflectionProperty(Container::class, 'services');
        $current = $table->getValue($services);
        $current[$id] = $service;
        $table->setValue($services, $current);
    }

    /** Every service the container holds now, so that what a test builds on its fake providers (ProvidersEditor, SignInJournal...) is dropped afterwards. */
    private function snapshotServices(): void
    {
        if ($this->servicesWere !== null) {
            return;
        }
        $services = self::getWiki()->services;
        $this->servicesWere = [];
        foreach (['services', 'privates'] as $name) {
            $this->servicesWere[$name] = (new \ReflectionProperty(Container::class, $name))->getValue($services);
        }
    }

    private function restoreServices(): void
    {
        if ($this->servicesWere === null) {
            return;
        }
        $services = self::getWiki()->services;
        foreach ($this->servicesWere as $name => $table) {
            (new \ReflectionProperty(Container::class, $name))->setValue($services, $table);
        }
        $this->servicesWere = null;
    }

    private function request(string $method, array $posted = []): void
    {
        self::getWiki()->services->get(CurrentRequest::class)->replace(Request::create('/?admin/sign-in/x/test', $method, $posted));
    }

    public function testTheScreenLeavesForARedirectProviderWithATestHandshake(): void
    {
        $controller = $this->controllerWith(['acme' => ['type' => 'fakeredirect', 'label' => 'Acme', 'enabled' => false], 'local' => ['type' => 'local']]);
        $this->request('GET');

        $response = $controller->test('acme');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://idp.example/', $response->headers->get('Location'));
        $handshake = $this->cookie->read(new Request(cookies: [HandshakeCookie::NAME => $this->issuedCookie(HandshakeCookie::NAME)]), time());
        $this->assertSame(HandshakeMode::Test, $handshake?->mode);
        $this->assertSame(self::ADMIN, $handshake->get('tested_by'), 'bound to the administrator who started it');
    }

    public function testTheScreenAsksAPasswordProviderForATestLogin(): void
    {
        $controller = $this->controllerWith(['dir' => ['type' => 'fakepw', 'label' => 'Directory'], 'local' => ['type' => 'local']]);
        $this->request('GET');

        $html = (string)$controller->test('dir')->getContent();

        $this->assertMatchesRegularExpression('#<input[^>]*type="password"[^>]*name="password"#', $html);
        $this->assertStringContainsString('admin/sign-in/dir/test', $html);
        $this->assertStringNotContainsString(htmlspecialchars(_t('SIGN_IN_TEST_BANNER'), ENT_QUOTES), $html, 'nothing has run yet');
    }

    public function testAPostedTestWithoutTheTokenRunsNothing(): void
    {
        $controller = $this->controllerWith(['dir' => ['type' => 'fakepw', 'label' => 'Directory'], 'local' => ['type' => 'local']]);
        $this->request('POST', ['login' => 'testmode', 'password' => 'good']);

        $html = (string)$controller->test('dir')->getContent();

        $this->assertStringNotContainsString(htmlspecialchars(_t('SIGN_IN_TEST_BANNER'), ENT_QUOTES), $html);
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_ADMIN_NOT_SAVED'), ENT_QUOTES), $html);
    }

    public function testAPostedTestShowsWhatCameBack(): void
    {
        $controller = $this->controllerWith(['dir' => ['type' => 'fakepw', 'label' => 'Directory'], 'local' => ['type' => 'local']], tokenAccepted: true);
        $this->request('POST', ['login' => 'testmode', 'password' => 'good']);
        $since = $this->latestJournalRow();

        $response = $controller->test('dir');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_TEST_BANNER'), ENT_QUOTES), (string)$response->getContent());
        $this->assertSame(0, $this->journalSince($since));
        $this->assertSame(0, $this->service->applied);
    }

    public function testAnUnknownProviderGoesBackToTheList(): void
    {
        $controller = $this->controllerWith(['local' => ['type' => 'local']]);
        $this->request('GET');

        $this->assertInstanceOf(RedirectResponse::class, $controller->test('nobody'));
    }

    public function testTheListOffersATest(): void
    {
        $controller = $this->controllerWith(['acme' => ['type' => 'fakeredirect', 'label' => 'Acme'], 'local' => ['type' => 'local'], 'gone' => ['type' => 'gone-extension']]);
        $config = sys_get_temp_dir() . '/test-mode-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($config, "<?php\n\n\$yeswikiConfig = " . var_export(['wakka_name' => 'test', 'sso_providers' => ['acme' => ['type' => 'fakeredirect', 'label' => 'Acme'], 'local' => ['type' => 'local'], 'gone' => ['type' => 'gone-extension']]], true) . ";\n");
        putenv('YESWIKI_CONFIG_FILE=' . $config);
        try {
            $html = (string)$controller->index()->getContent();
        } finally {
            putenv('YESWIKI_CONFIG_FILE');
            @unlink($config);
        }

        $this->assertStringContainsString('admin/sign-in/acme/test', $html);
        $this->assertStringNotContainsString('admin/sign-in/local/test', $html, 'the wiki\'s own accounts are not tested');
        $this->assertStringNotContainsString('admin/sign-in/gone/test', $html, 'nor a provider whose type is gone');
    }

    /** Boosted, the link would fetch the provider's 302 as an XHR across origins: the browser must leave for real, as from the list. */
    public function testTestingAgainLeavesWithoutBoostedNavigation(): void
    {
        $html = $this->renderTested($this->flow->callback($this->testCallback(), 'fake'));

        $this->assertMatchesRegularExpression('#<a [^>]*href="[^"]*admin/sign-in/fake/test"[^>]*hx-boost="false"#', $html);
    }

    public function testTheStepsAreNamedOnTheScreen(): void
    {
        $outcome = SignInOutcome::tested(
            ProviderConfig::fromArray('fake', ['type' => 'fake-redirect', 'label' => 'Fake SSO']),
            new SignInPlan('fake', null, PlanAction::Refuse, null, FailureReason::ProviderUnreachable, '', [], [], [], [], [], [], [], ''),
            [['step' => SignInTrace::SENT_TO_PROVIDER, 'state' => 'failed']],
            'timeout'
        );

        $html = $this->renderTested($outcome);

        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_TEST_STEP_SENT_TO_PROVIDER'), ENT_QUOTES), $html);
        $this->assertStringContainsString('timeout', $html);
    }
}
