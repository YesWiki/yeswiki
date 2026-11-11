<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Content\Service\PageManager;
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
use YesWiki\Identity\Service\UserManager;
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
use YesWiki\Identity\SignIn\SignInOutcome;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A redirect provider whose answer each test decides. */
final class FlowFakeRedirect implements RedirectProvider
{
    public ?ProviderConfig $config = null;

    /** @var array<string, mixed> */
    public array $attributes = ['sub' => 'flow-new', 'email' => 'flow-new@example.com', 'given_name' => 'Flow', 'family_name' => 'New'];

    public ?\Throwable $failure = null;

    public ?\Throwable $failureAtBegin = null;

    public int $completed = 0;

    public function failWith(\Throwable $failure): void
    {
        $this->failure = $failure;
    }

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
        $handshake->set('state', 'kept-across-the-trip');

        return new RedirectResponse('https://idp.example/authorize');
    }

    public function complete(Request $request, Handshake $handshake): ProviderIdentity
    {
        $this->completed++;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($handshake->get('state') !== 'kept-across-the-trip') {
            throw new ProviderFailure(FailureReason::InvalidResponse, 'the state did not survive the trip');
        }

        return new ProviderIdentity($this->attributes);
    }
}

/** A directory that accepts the password "good". */
final class FlowFakePassword implements PasswordProvider
{
    public ?ProviderConfig $config = null;

    public int $calls = 0;

    public string $lastLogin = '';

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
        $this->lastLogin = $login;
        if ($password !== 'good') {
            throw new ProviderFailure(FailureReason::WrongCredentials, 'bad password');
        }

        // local_account is what the local type reads; every other type ignores it.
        return new ProviderIdentity(['sub' => 'flow-pw', 'email' => 'flow-pw@example.com', 'local_account' => 'FlowLocal']);
    }
}

class SignInFlowTest extends YesWikiTestCase
{
    private const EMAILS = ['flow-local@example.com', 'flow-new@example.com', 'flow-pw@example.com', 'flow-linked@example.com', 'flow-taken@example.com'];
    private const TAGS = ['FlowLinked', 'FlowOther', 'FlowLocal'];
    private const LOGINS = ['locked', 'x', 'flow', 'mixedcase'];

    private SignInFlow $flow;
    private FlowFakeRedirect $fake;
    private FlowFakePassword $fakePw;
    private HandshakeCookie $cookie;
    private Request $requestWas;

    /** @var array<string, mixed> */
    private array $cookiesWere;

    private int $journalFloor = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->requestWas = $services->get(CurrentRequest::class)->get();
        $this->cookiesWere = $_COOKIE;
        unset($_COOKIE[HandshakeCookie::NAME], $_COOKIE[LoginCookie::NAME]);
        $this->cleanUp();
        self::getWiki()->services->get(UserManager::class)->create('FlowLocal', 'flow-local@example.com', 'a-long-enough-password');
        $this->journalFloor = (int)$this->db()->scalar("SELECT COALESCE(MAX({$this->db()->quoteIdentifier('id')}), 0) FROM {$this->journal()}", 0);

        $this->fake = new FlowFakeRedirect();
        $this->fakePw = new FlowFakePassword();
        $this->cookie = $services->get(HandshakeCookie::class);
        $this->flow = $this->flowFor([
            'fake' => ['type' => 'fake-redirect', 'label' => 'Fake SSO'],
            'off' => ['type' => 'fake-redirect', 'enabled' => false],
            'legacy' => ['type' => 'fake-redirect', 'connection' => ['legacy_callback' => 'https://old.example/?api/auth_sso/callback=']],
            'fakepw' => ['type' => 'fake-password'],
            'local' => ['type' => 'local'],
            'orphan' => ['type' => 'not-installed'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        $this->db()->query("DELETE FROM {$this->journal()} WHERE {$this->db()->quoteIdentifier('id')} > {$this->journalFloor} AND {$this->db()->quoteIdentifier('action')} IN ('login.failed', 'login.throttled')");
        self::getWiki()->services->get(CurrentRequest::class)->replace($this->requestWas);
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
        $tags = self::TAGS;
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
            . " OR ({$action} IN ('login.failed', 'login.throttled') AND ({$target} IN ({$logins}) OR {$target} LIKE 'flow-%'))");
    }

    /** @param array<string, array<string, mixed>> $section */
    private function flowFor(array $section, ?AuthenticationService $browser = null): SignInFlow
    {
        $services = self::getWiki()->services;
        $values = ['sso_providers' => $section];
        $config = new RuntimeConfig();
        $config->bind($values);
        $registry = new ProviderRegistry([
            $this->fakeType('fake-redirect', $this->fake),
            $this->fakeType('fake-password', $this->fakePw),
            $this->fakeType('local', $this->fakePw),
        ], $config);
        $journal = new SignInJournal($services->get(Journal::class), $services->get(AuthenticationService::class), $services->get(AttemptLimiter::class), $registry);

        return new SignInFlow($registry, $services->get(SignInService::class), $journal, $services->get(AttemptLimiter::class), $services->get(HandshakeCookie::class), $browser ?? $this->browser(), $services->get(UrlFormatter::class), $services->get(AclService::class));
    }

    private function fakeType(string $name, FlowFakeRedirect|FlowFakePassword $provider): ProviderType
    {
        return new class($name, $provider) implements ProviderType {
            public function __construct(private string $name, private FlowFakeRedirect|FlowFakePassword $provider)
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

    /** The service as a web request has it: the one that really sends yw-auth. */
    private function browser(): AuthenticationService
    {
        $services = self::getWiki()->services;
        $browser = new class($services->get(AccountActivationService::class), $services->get(HibernationService::class), $services->get(ParameterBagInterface::class), $services->get(PasswordHasherFactory::class), $services->get(UserManager::class), $services) extends AuthenticationService {
            protected function isCli(): bool
            {
                return false;
            }
        };
        $browser->setServices($services);

        return $browser;
    }

    /** What the flow answered, which this test expects to be an outcome rather than a redirect. */
    private function outcome(Response|SignInOutcome $answer): SignInOutcome
    {
        $this->assertInstanceOf(SignInOutcome::class, $answer);

        return $answer;
    }

    /** What the flow answered, which this test expects to be a redirect rather than an outcome. */
    private function response(Response|SignInOutcome $answer): Response
    {
        $this->assertInstanceOf(Response::class, $answer);

        return $answer;
    }

    private function issuedCookie(string $name): string
    {
        return (string)($_COOKIE[$name] ?? '');
    }

    /** The name of the cookie when it is gone; '' while it is still there. */
    private function clearedCookie(string $name): string
    {
        return array_key_exists($name, $_COOKIE) ? '' : $name;
    }

    /** The browser's return from the provider: the handshake begin() really issued comes back. */
    private function callbackRequest(string $provider = 'fake'): Request
    {
        $this->assertInstanceOf(Response::class, $this->flow->begin($provider, '/?PagePrincipale'));
        $this->assertNotSame('', $this->issuedCookie(HandshakeCookie::NAME), 'a handshake to come back with');

        return new Request(query: ['code' => 'from-the-provider'], cookies: [HandshakeCookie::NAME => $this->issuedCookie(HandshakeCookie::NAME)]);
    }

    /** The provider sends an email another account holds, for an account that is already linked. */
    private function callbackRequestWithTakenEmail(): Request
    {
        $services = self::getWiki()->services;
        $users = $services->get(UserManager::class);
        $linked = $users->create('FlowLinked', 'flow-linked@example.com', 'a-long-enough-password');
        $users->create('FlowOther', 'flow-taken@example.com', 'a-long-enough-password');
        $this->assertNotNull($linked);
        $services->get(AccountLinks::class)->link($linked, 'fake', 'flow-linked-subject');
        $this->fake->attributes = ['sub' => 'flow-linked-subject', 'email' => 'flow-taken@example.com'];

        return $this->callbackRequest();
    }

    private function requestWithCookie(?string $cookie): Request
    {
        $value = match ($cookie) {
            null => null,
            '@expired' => $this->cookie->encode(new Handshake('fake', '/', 'https://w/cb', HandshakeMode::Real, time() - 10)),
            '@other-provider' => $this->cookie->encode(new Handshake('fakepw', '/', 'https://w/cb', HandshakeMode::Real, time() + 300)),
            default => $cookie,
        };

        return new Request(cookies: $value === null ? [] : [HandshakeCookie::NAME => $value]);
    }

    private function lastHandshake(): Handshake
    {
        $handshake = $this->cookie->read(new Request(cookies: [HandshakeCookie::NAME => $this->issuedCookie(HandshakeCookie::NAME)]), time());
        $this->assertNotNull($handshake);

        return $handshake;
    }

    private function countJournal(string $where): int
    {
        $id = $this->db()->quoteIdentifier('id');

        return (int)$this->db()->scalar("SELECT COUNT(*) FROM {$this->journal()} WHERE ({$where}) AND {$id} > {$this->journalFloor}", 0);
    }

    /** @return array<string, mixed> */
    private function diagnosticContext(string $action): array
    {
        $row = $this->db()->loadSingle("SELECT * FROM {$this->journal()} WHERE action = ? ORDER BY id DESC LIMIT 1", [$action]);
        $this->assertIsArray($row, $action);

        return (array)json_decode((string)$row['context'], true);
    }

    /** @return array{name: string, issued: int, expires: int, remember: bool, generation: int} */
    private function loginCookie(): array
    {
        $read = self::getWiki()->services->get(LoginCookie::class)->read($this->issuedCookie(LoginCookie::NAME), time());
        $this->assertIsArray($read, 'yw-auth was issued');

        return $read;
    }

    public function testBeginRedirectsAndIssuesTheHandshake(): void
    {
        $response = $this->response($this->flow->begin('fake', '/?PagePrincipale'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertNotEmpty($this->issuedCookie('yw-sign-in'));
    }

    public function testTheHandshakeRemembersThatTrip(): void
    {
        $this->flow->begin('fake', '');
        $handshake = $this->lastHandshake();

        $this->assertSame('fake', $handshake->providerId);
        $this->assertSame($this->flow->callbackUrlFor(self::providerConfig('fake')), $handshake->callbackUrl);
        $this->assertSame(HandshakeMode::Real, $handshake->mode);
        $this->assertEqualsWithDelta(time() + HandshakeCookie::LIFETIME, $handshake->expiresAt, 2);
        $this->assertSame('kept-across-the-trip', $handshake->get('state'), 'what the provider noted is in the cookie');
    }

    public function testAnExternalReturnUrlIsDropped(): void
    {
        $this->flow->begin('fake', 'https://evil.example/');
        $this->assertStringNotContainsString('evil.example', $this->lastHandshake()->returnUrl);
    }

    public function testAnInternalReturnUrlIsKept(): void
    {
        $url = self::getWiki()->services->get(UrlFormatter::class)->href('', 'PagePrincipale');
        $this->flow->begin('fake', $url);

        $this->assertSame($url, $this->lastHandshake()->returnUrl);
    }

    public function testADisabledProviderIsRefused(): void
    {
        $this->assertSame(FailureReason::ProviderDisabled, $this->outcome($this->flow->begin('off', '/'))->reason);
        $this->assertSame('', $this->issuedCookie('yw-sign-in'));
    }

    public function testAnUninstalledTypeIsRefusedLikeASwitchedOffProvider(): void
    {
        $outcome = $this->outcome($this->flow->begin('orphan', '/'));

        $this->assertSame(FailureReason::ProviderDisabled, $outcome->reason);
        $this->assertSame('orphan', $outcome->config?->id);
    }

    public function testAnUnknownProviderIsRefusedAndNothingIsRecorded(): void
    {
        $outcome = $this->outcome($this->flow->begin('nobody-configured-this', '/'));

        $this->assertSame(FailureReason::ProviderDisabled, $outcome->reason);
        $this->assertNull($outcome->config);
        $this->assertSame(0, $this->countJournal("action LIKE 'login.%'"));
    }

    public function testAPasswordProviderHasNoRedirectToBegin(): void
    {
        $outcome = $this->outcome($this->flow->begin('fakepw', '/'));

        $this->assertSame(FailureReason::ConfigurationInvalid, $outcome->reason);
        $this->assertSame('', $this->issuedCookie('yw-sign-in'));
    }

    public function testAFailureWhileLeavingIsRefusedAndNoHandshakeIsIssued(): void
    {
        $this->fake->failureAtBegin = new ProviderFailure(FailureReason::ProviderUnreachable, 'discovery timed out');

        $outcome = $this->outcome($this->flow->begin('fake', '/'));

        $this->assertSame(FailureReason::ProviderUnreachable, $outcome->reason);
        $this->assertSame('', $this->issuedCookie('yw-sign-in'));
        $this->assertSame(1, $this->countJournal("action = 'login.refused.provider_unreachable.fake'"));
    }

    public function testAnUnexpectedExceptionWhileLeavingBecomesInternalError(): void
    {
        $this->fake->failureAtBegin = new \LogicException('boom');

        $this->assertSame(FailureReason::InternalError, $this->outcome($this->flow->begin('fake', '/'))->reason);
    }

    public function testASuccessfulCallbackSignsInRememberedAndWritesNoJournal(): void
    {
        $outcome = $this->outcome($this->flow->callback($this->callbackRequest(), 'fake'));
        $this->assertSame('signed_in', $outcome->kind);
        $this->assertSame(0, $this->countJournal("action LIKE 'login.%'"));
        $this->assertNotEmpty($this->issuedCookie('yw-auth'));

        $cookie = $this->loginCookie();
        $this->assertTrue($cookie['remember'], 'a provider sign-in lasts 90 days');
        $this->assertSame($cookie['issued'] + LoginCookie::REMEMBERED_LIFETIME, $cookie['expires']);
    }

    public function testTheVisitorGoesBackWhereTheHandshakeSaid(): void
    {
        $url = self::getWiki()->services->get(UrlFormatter::class)->href('', 'PagePrincipale');
        $this->flow->begin('fake', $url);
        $request = new Request(cookies: [HandshakeCookie::NAME => $this->issuedCookie(HandshakeCookie::NAME)]);

        $this->assertSame($url, $this->outcome($this->flow->callback($request, 'fake'))->url);
    }

    public function testWithoutAnIdTheProviderComesFromTheHandshake(): void
    {
        $this->assertSame('signed_in', $this->outcome($this->flow->callback($this->callbackRequest(), null))->kind);
    }

    public function testAWarningIsRecordedWithoutRecordingTheSuccess(): void
    {
        // the account's new email is already used by another account
        $this->assertSame('signed_in', $this->outcome($this->flow->callback($this->callbackRequestWithTakenEmail(), 'fake'))->kind);
        $this->assertSame(1, $this->countJournal("action = 'login.warning.email_collision.fake'"));
        $this->assertSame(0, $this->countJournal("action = 'login.failed'"));
    }

    public function testAWarningIsWrittenOnce(): void
    {
        $this->flow->callback($this->callbackRequestWithTakenEmail(), 'fake');

        $row = $this->db()->loadSingle("SELECT * FROM {$this->journal()} WHERE action = 'login.warning.email_collision.fake'");
        $this->assertIsArray($row);
        $this->assertSame(1, (int)$row['repeat']);
        $this->assertSame('flow-taken@example.com', $row['target']);
    }

    public function testAProviderFailureIsRefusedAndJournaled(): void
    {
        $this->fake->failWith(new ProviderFailure(FailureReason::ProviderRejected, 'access_denied'));
        $outcome = $this->outcome($this->flow->callback($this->callbackRequest(), 'fake'));
        $this->assertSame(FailureReason::ProviderRejected, $outcome->reason);
        $this->assertSame(1, $this->countJournal("action = 'login.refused.provider_rejected.fake'"));
        $this->assertSame(1, $this->countJournal("action = 'login.failed'"));
    }

    public function testAProviderFailureKeepsItsMessageForThePersonApart(): void
    {
        $this->fake->failWith(new ProviderFailure(FailureReason::ProviderRejected, 'admin text', 'Ask your manager'));

        $outcome = $this->outcome($this->flow->callback($this->callbackRequest(), 'fake'));

        $this->assertSame('admin text', $outcome->detail);
        $this->assertSame('Ask your manager', $outcome->message);
    }

    public function testAFailureRecordsHowFarItGotAndWhatCameBack(): void
    {
        $this->fake->failWith(new ProviderFailure(FailureReason::InvalidResponse, 'bad signature', null, ['sub' => 'someone']));

        $this->flow->callback($this->callbackRequest(), 'fake');

        $context = $this->diagnosticContext('login.refused.invalid_response.fake');
        $this->assertSame('provider_answered', $context['last_step']);
        $this->assertSame('bad signature', $context['error']);
        $this->assertArrayHasKey('sub', $context['attributes']);
    }

    public function testARefusedPlanIsRefusedWithWhatThePlanKnew(): void
    {
        $this->flow = $this->flowFor(['fake' => ['type' => 'fake-redirect', 'create_accounts' => false]]);

        $outcome = $this->outcome($this->flow->callback($this->callbackRequest(), 'fake'));

        $this->assertSame(FailureReason::NoAccount, $outcome->reason);
        $this->assertSame('flow-new@example.com', $outcome->who);
        $this->assertSame('', $this->issuedCookie('yw-auth'));
        $this->assertSame(1, $this->countJournal("action = 'login.refused.no_account.fake'"));
        $this->assertSame(1, $this->countJournal("action = 'login.failed' AND target = 'flow-new@example.com'"));

        $context = $this->diagnosticContext('login.refused.no_account.fake');
        $this->assertSame('attributes_read', $context['last_step']);
        $this->assertSame('sub', $context['attribute_use']['id']);
    }

    public function testAMissingAttributeIsNamedToThePerson(): void
    {
        $this->flow = $this->flowFor(['fake' => ['type' => 'fake-redirect', 'label' => 'Fake SSO', 'attributes' => ['email' => 'mail']]]);
        $this->fake->attributes = ['sub' => 'flow-nomail', 'given_name' => 'No'];

        $outcome = $this->outcome($this->flow->callback($this->callbackRequest(), 'fake'));

        $this->assertSame(FailureReason::MissingAttribute, $outcome->reason);
        $this->assertMatchesRegularExpression('/["«]\s?mail\s?["»]/u', $outcome->messageForPerson(), 'the attribute is named, quoted');
        $this->assertStringContainsString('Fake SSO', $outcome->messageForPerson());
        $this->assertStringContainsString('"mail"', (string)$this->diagnosticContext('login.refused.missing_attribute.fake')['error']);
    }

    public function testAMissingAttributeKeepsTheProvidersOwnMessage(): void
    {
        $this->fake->failWith(new ProviderFailure(FailureReason::MissingAttribute, 'no uid', 'Ask IT to release your uid'));

        $this->assertSame('Ask IT to release your uid', $this->outcome($this->flow->callback($this->callbackRequest(), 'fake'))->messageForPerson());
    }

    public function testAnUnexpectedExceptionBecomesInternalError(): void
    {
        $this->fake->failWith(new \LogicException('boom'));
        $this->assertSame(FailureReason::InternalError, $this->outcome($this->flow->callback($this->callbackRequest(), 'fake'))->reason);
    }

    public function testASwitchedOffProviderIsRefusedOnTheWayBack(): void
    {
        $request = $this->callbackRequest();
        $this->flow = $this->flowFor(['fake' => ['type' => 'fake-redirect', 'enabled' => false]]);

        $outcome = $this->outcome($this->flow->callback($request, 'fake'));

        $this->assertSame(FailureReason::ProviderDisabled, $outcome->reason);
        $this->assertSame(0, $this->fake->completed);
    }

    // Review Focus: a callback with no valid handshake.
    /** @return array<string, array{?string}> */
    public static function badHandshakes(): array
    {
        return [
            'other browser' => [null],
            'garbage' => ['garbage'],
            'expired' => ['@expired'],
            'for another provider' => ['@other-provider'],
        ];
    }

    #[DataProvider('badHandshakes')]
    public function testNoValidHandshakeIsARefusedPageNotACrash(?string $cookie): void
    {
        $outcome = $this->outcome($this->flow->callback($this->requestWithCookie($cookie), 'fake'));
        $this->assertSame('refused', $outcome->kind);
        $this->assertSame(FailureReason::InvalidResponse, $outcome->reason);
        $this->assertSame(0, $this->fake->completed, 'the provider is never asked');
    }

    public function testNoHandshakeAndNoIdIsARefusedPageNotACrash(): void
    {
        $outcome = $this->outcome($this->flow->callback(new Request(), null));

        $this->assertSame(FailureReason::InvalidResponse, $outcome->reason);
        $this->assertNull($outcome->config);
    }

    public function testAHandshakeCannotBeReplayed(): void
    {
        $this->flow->callback($this->callbackRequest(), 'fake');
        $this->assertNotEmpty($this->clearedCookie('yw-sign-in'));
    }

    public function testAHandshakeIsSpentEvenWhenTheProviderRefuses(): void
    {
        $this->fake->failWith(new ProviderFailure(FailureReason::ProviderRejected));

        $this->flow->callback($this->callbackRequest(), 'fake');

        $this->assertNotEmpty($this->clearedCookie('yw-sign-in'));
    }

    public function testBlockedLoginNeverReachesTheProvider(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->flow->password('fakepw', 'locked', 'bad', false);
        }
        $outcome = $this->flow->password('fakepw', 'locked', 'good', false);
        $this->assertSame(FailureReason::TooManyAttempts, $outcome->reason);
        $this->assertSame(5, $this->fakePw->calls);
        $this->assertSame(1, $this->countJournal("action = 'login.throttled' AND target = 'locked'"));
        $this->assertSame(5, $this->countJournal("action = 'login.failed' AND target = 'locked'"), 'being stopped does not count as a failure');
    }

    public function testTheCountCannotBeDodgedByCaseOrSpaces(): void
    {
        foreach (['MixedCase', ' mixedcase', 'MIXEDCASE ', 'mixedCase', '  MiXeDcAsE  '] as $login) {
            $this->flow->password('fakepw', $login, 'bad', false);
        }
        $this->assertSame(5, $this->countJournal("action = 'login.failed' AND target = 'mixedcase'"));

        $this->assertSame(FailureReason::TooManyAttempts, $this->flow->password('fakepw', ' MIXEDcase ', 'good', false)->reason);
        $this->assertSame(5, $this->fakePw->calls);
    }

    public function testWrongCredentialsStayOnTheForm(): void
    {
        $outcome = $this->flow->password('fakepw', 'x', 'bad', false);

        $this->assertSame('form_error', $outcome->kind);
        $this->assertSame(_t('SIGN_IN_REFUSED_WRONG_CREDENTIALS'), $outcome->message);
        $this->assertSame(1, $this->countJournal("action = 'login.failed' AND target = 'x'"));
        $this->assertSame(0, $this->countJournal("action LIKE 'login.refused.%'"), 'a wrong password is not a diagnostic');
    }

    public function testAPasswordProviderSignsInAndIsRememberedWhateverTheCheckbox(): void
    {
        $outcome = $this->flow->password('fakepw', 'flow', 'good', false);

        $this->assertSame('signed_in', $outcome->kind);
        $this->assertTrue($this->loginCookie()['remember'], 'only local keeps its checkbox');
        $this->assertSame(0, $this->countJournal("action LIKE 'login.%'"));
    }

    /** @return array<string, array{bool}> */
    public static function checkbox(): array
    {
        return ['ticked' => [true], 'unticked' => [false]];
    }

    #[DataProvider('checkbox')]
    public function testLocalKeepsItsCheckbox(bool $remember): void
    {
        $this->assertSame('signed_in', $this->flow->password('local', 'flow', 'good', $remember)->kind);

        $this->assertSame($remember, $this->loginCookie()['remember']);
    }

    public function testAnAccountNotYetActivatedIsToldOnTheForm(): void
    {
        $notActivated = new class(_t('ACCOUNTACTIVATION_BY_EMAIL_WARNING', ['message' => 'sent'])) extends AuthenticationService {
            public function __construct(private string $message)
            {
            }

            public function login($user, $remember = 0): void
            {
                throw new \YesWiki\Identity\Exception\BadLoginException($this->message);
            }
        };
        $flow = $this->flowFor(['local' => ['type' => 'local']], $notActivated);

        $outcome = $flow->password('local', 'flow', 'good', false);

        $this->assertSame('form_error', $outcome->kind);
        $this->assertSame(_t('ACCOUNTACTIVATION_BY_EMAIL_WARNING', ['message' => 'sent']), $outcome->message);
        $this->assertSame(0, $this->countJournal("action LIKE 'login.%'"), 'waiting for the activation mail is not a failure');
    }

    public function testADisabledPasswordProviderIsRefusedBeforeTheDirectoryIsAsked(): void
    {
        $this->flow = $this->flowFor(['fakepw' => ['type' => 'fake-password', 'enabled' => false]]);

        $outcome = $this->flow->password('fakepw', 'flow', 'good', false);

        $this->assertSame(FailureReason::ProviderDisabled, $outcome->reason);
        $this->assertSame(0, $this->fakePw->calls);
    }

    public function testARedirectProviderTakesNoPassword(): void
    {
        $this->assertSame(FailureReason::ConfigurationInvalid, $this->flow->password('fake', 'flow', 'good', false)->reason);
    }

    public function testARefusalAfterTheDirectorySaidYesCountsAgainstTheLogin(): void
    {
        $this->flow = $this->flowFor(['fakepw' => ['type' => 'fake-password', 'create_accounts' => false]]);

        $outcome = $this->flow->password('fakepw', ' FLOW ', 'good', false);

        $this->assertSame(FailureReason::NoAccount, $outcome->reason);
        $this->assertSame('flow', $outcome->who);
        $this->assertSame(1, $this->countJournal("action = 'login.failed' AND target = 'flow'"));
    }

    /** A provider without a label of its own is named to the person by its type's label, not by its identifier. */
    public function testThePersonReadsTheProvidersLabelNotItsIdentifier(): void
    {
        $services = self::getWiki()->services;
        $owner = $services->get(UserManager::class)->create('FlowOther', 'flow-pw@example.com', 'a-long-enough-password');
        $this->assertNotNull($owner);
        $services->get(AccountLinks::class)->link($owner, 'fake', 'someone-else');

        $outcome = $this->flow->password('fakepw', 'flow', 'good', false);

        $this->assertSame(FailureReason::LinkedElsewhere, $outcome->reason);
        $this->assertStringContainsString('fake-password', $outcome->messageForPerson(), 'the type\'s label');
        $this->assertStringNotContainsString('fakepw', $outcome->messageForPerson());
    }

    public function testTheDirectoryGetsTheLoginAsTyped(): void
    {
        $this->assertSame('signed_in', $this->flow->password('fakepw', 'FLOW-Typed', 'good', false)->kind);

        $this->assertSame('FLOW-Typed', $this->fakePw->lastLogin);
    }

    public function testTheCallbackUrlIsTheRouteAndAbsolute(): void
    {
        $base = (string)self::getWiki()->services->get(RuntimeConfig::class)['base_url'];

        $this->assertSame($base . 'user/sign-in/fake/callback', $this->flow->callbackUrlFor(self::providerConfig('fake')));
    }

    public function testALegacyCallbackStaysWhatTheProviderHasRegistered(): void
    {
        $config = ProviderConfig::fromArray('legacy', ['type' => 'oidc', 'connection' => ['legacy_callback' => 'https://old.example/?api/auth_sso/callback=']]);

        $this->assertSame('https://old.example/?api/auth_sso/callback=', $this->flow->callbackUrlFor($config));
    }

    public function testRefusedAndSignedInOutcomesCarryOnlyWhatTheyAreFor(): void
    {
        $refused = SignInOutcome::refused(null, FailureReason::NoAccount, 'who');
        $this->assertSame('refused', $refused->kind);
        $this->assertSame('', $refused->url);
        $this->assertSame('', $refused->detail);

        $signedIn = SignInOutcome::signedIn('/there');
        $this->assertSame('signed_in', $signedIn->kind);
        $this->assertSame('/there', $signedIn->url);
        $this->assertNull($signedIn->reason);
    }

    private static function providerConfig(string $id): ProviderConfig
    {
        return ProviderConfig::fromArray($id, ['type' => 'fake-redirect']);
    }
}
