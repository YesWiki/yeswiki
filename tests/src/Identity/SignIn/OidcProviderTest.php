<?php

namespace YesWiki\Test\Identity\SignIn;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Service\SignIn\OidcDiscovery;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Identity\SignIn\Contract\HandshakeMode;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\Provider\OidcProvider;

require_once __DIR__ . '/OidcTestKeys.php';

class OidcProviderTest extends TestCase
{
    use OidcTestKeys;

    private const CALLBACK = 'https://wiki.example/?user/sign-in/acme/callback&sso_callback=acme';
    private const SECRET = 'the-client-secret';
    private const DISCOVERY = 'https://idp.example/.well-known/openid-configuration';

    /** What a migrated loginsso provider holds: plain OAuth2 endpoints, nothing to check a token with. */
    private const EXPLICIT = [
        'authorize_url' => 'https://idp.example/authorize',
        'token_url' => 'https://idp.example/token',
        'userinfo_url' => 'https://idp.example/userinfo',
        'client_id' => self::CLIENT_ID,
        'client_secret' => self::SECRET,
    ];

    /** @var list<RequestInterface> what the provider sent, in order */
    private array $sent = [];

    /** @var array<string, list<Response|\Throwable>> what each path answers, in turn */
    private array $answers = [];

    protected function setUp(): void
    {
        $this->sent = [];
        $this->answers = [];
    }

    /** @param array<string, mixed> $connection */
    private function config(array $connection = self::EXPLICIT): ProviderConfig
    {
        return ProviderConfig::fromArray('acme', ['type' => 'oidc', 'label' => 'Acme', 'connection' => $connection]);
    }

    /** @return array<string, mixed> */
    private static function discoveryConnection(): array
    {
        return ['discovery_url' => self::DISCOVERY, 'client_id' => self::CLIENT_ID, 'client_secret' => self::SECRET];
    }

    private static function discoveryDocument(): string
    {
        return (string)json_encode([
            'issuer' => self::ISSUER,
            'authorization_endpoint' => 'https://idp.example/oauth2/authorize',
            'token_endpoint' => 'https://idp.example/oauth2/token',
            'userinfo_endpoint' => 'https://idp.example/oauth2/userinfo',
            'jwks_uri' => 'https://idp.example/oauth2/jwks',
        ]);
    }

    /** @param ?MockHttpClient $discovery what the discovery address answers; by default the document above, each time */
    private function provider(?ProviderConfig $config = null, ?MockHttpClient $discovery = null): OidcProvider
    {
        $discovery ??= new MockHttpClient(fn (): MockResponse => new MockResponse(self::discoveryDocument()));
        $handler = function (RequestInterface $request): PromiseInterface {
            $this->sent[] = $request;
            $answer = array_shift($this->answers[basename($request->getUri()->getPath())]);
            if ($answer === null) {
                $this->fail('nothing was expected at ' . $request->getUri());
            }

            return $answer instanceof \Throwable ? Create::rejectionFor($answer) : Create::promiseFor($answer);
        };

        return new OidcProvider($config ?? $this->config(), new OidcDiscovery($discovery), new Client(['handler' => HandlerStack::create($handler)]));
    }

    private function answer(string $path, Response|\Throwable ...$answers): void
    {
        $this->answers[$path] = array_values(array_merge($this->answers[$path] ?? [], $answers));
    }

    private function handshake(string $callback = self::CALLBACK): Handshake
    {
        return new Handshake('acme', '/', $callback, HandshakeMode::Real, time() + 600);
    }

    /** A handshake as begin() leaves it. */
    private function begun(?ProviderConfig $config = null, string $callback = self::CALLBACK): Handshake
    {
        $handshake = $this->handshake($callback);
        $this->provider($config)->begin($handshake);

        return $handshake;
    }

    /** @param array<string, mixed> $extra */
    private static function tokenResponse(array $extra = [], int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string)json_encode(['access_token' => 'the-access-token', 'token_type' => 'Bearer', 'expires_in' => 300] + $extra));
    }

    /** @param array<string, mixed> $claims */
    private static function userinfoResponse(array $claims = []): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], (string)json_encode($claims + [
            'sub' => 'marie',
            'email' => 'm@x',
            'given_name' => 'Marie',
            'family_name' => 'Dupont',
            'groups' => ['wiki-editors', 'staff'],
        ]));
    }

    /** @param array<string, string> $query the provider's answer on the callback, with the right state unless it says otherwise */
    private function complete(Handshake $handshake, array $query = [], ?ProviderConfig $config = null): ProviderIdentity
    {
        return $this->provider($config)->complete(new Request(query: $query + ['state' => (string)$handshake->get('state'), 'code' => 'the-code']), $handshake);
    }

    private function completeOk(?ProviderConfig $config = null): ProviderIdentity
    {
        $this->answer('token', self::tokenResponse());
        $this->answer('userinfo', self::userinfoResponse());

        return $this->complete($this->begun($config), [], $config);
    }

    /**
     * @param array<string, string> $query
     * @param ?Handshake            $handshake a begun one by default
     */
    private function assertFails(FailureReason $reason, array $query = [], ?ProviderConfig $config = null, ?Handshake $handshake = null): ProviderFailure
    {
        try {
            $this->complete($handshake ?? $this->begun($config), $query, $config);
        } catch (ProviderFailure $failure) {
            $this->assertSame($reason, $failure->reason, $failure->adminDetail);
            foreach ([self::SECRET, 'the-code', 'the-access-token'] as $secret) {
                $this->assertStringNotContainsString($secret, $failure->adminDetail, 'no secret, code or token in the detail');
            }

            return $failure;
        }
        $this->fail('the sign-in went through');
    }

    /** @return array<string, string> */
    private static function queryOf(string $url): array
    {
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

        return self::strings($query);
    }

    /** @return array<string, string> */
    private static function formOf(RequestInterface $request): array
    {
        parse_str((string)$request->getBody(), $form);

        return self::strings($form);
    }

    /**
     * The single values of a parsed query: a key sent as a list is not one of the parameters these tests look at.
     *
     * @param array<array-key, mixed> $parsed
     *
     * @return array<string, string>
     */
    private static function strings(array $parsed): array
    {
        $strings = [];
        foreach ($parsed as $key => $value) {
            if (is_string($value)) {
                $strings[(string)$key] = $value;
            }
        }

        return $strings;
    }

    private function sentTo(string $path): RequestInterface
    {
        foreach ($this->sent as $request) {
            if (basename($request->getUri()->getPath()) === $path) {
                return $request;
            }
        }
        $this->fail("nothing was sent to $path");
    }

    public function testBeginStoresStateNonceAndPkce(): void
    {
        $h = $this->handshake();
        $url = $this->provider()->begin($h)->headers->get('Location');
        $this->assertNotNull($url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
        $this->assertStringContainsString('state=' . $h->get('state'), $url);
        $this->assertStringContainsString('nonce=' . $h->get('nonce'), $url);
        $this->assertNotEmpty($h->get('pkce'));

        $query = self::queryOf((string)$url);
        $this->assertStringStartsWith('https://idp.example/authorize?', (string)$url);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', (string)$h->get('pkce'), true)), '+/', '-_'), '='), $query['code_challenge'], 'the challenge is the S256 of the verifier kept');
        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertNotSame($h->get('state'), $this->begun()->get('state'), 'a new state each time');
        $this->assertSame([], $this->sent, 'nothing is fetched to leave with explicit endpoints');
    }

    public function testConfiguredScopesAndSeparatorAreSent(): void
    {
        $url = $this->provider($this->config(self::EXPLICIT + ['scopes' => 'openid  profile groups']))->begin($this->handshake())->headers->get('Location');

        $this->assertSame('openid profile groups', self::queryOf((string)$url)['scope']);
        $url = $this->provider($this->config(self::EXPLICIT + ['scopes' => ['openid', 'groups'], 'scope_separator' => ',']))->begin($this->handshake())->headers->get('Location');
        $this->assertSame('openid,groups', self::queryOf((string)$url)['scope']);
    }

    public function testExplicitEndpointsUseTheHandshakeRedirectUri(): void
    {
        $callback = self::CALLBACK;
        $config = $this->config(self::EXPLICIT);
        $handshake = $this->handshake($callback);
        $url = (string)$this->provider($config)->begin($handshake)->headers->get('Location');
        $this->assertSame($callback, self::queryOf($url)['redirect_uri']);

        $this->answer('token', self::tokenResponse());
        $this->answer('userinfo', self::userinfoResponse());
        $this->complete($handshake, [], $config);

        $token = self::formOf($this->sentTo('token'));
        $this->assertSame('POST', $this->sentTo('token')->getMethod());
        $this->assertSame($callback, $token['redirect_uri']);
        $this->assertSame('the-code', $token['code']);
        $this->assertSame((string)$handshake->get('pkce'), $token['code_verifier'], 'the verifier comes back from the handshake');
        $this->assertSame(self::CLIENT_ID, $token['client_id']);
        $this->assertSame('Bearer the-access-token', $this->sentTo('userinfo')->getHeaderLine('Authorization'));
    }

    public function testAWrongStateIsAnInvalidResponse(): void
    {
        $this->assertFails(FailureReason::InvalidResponse, ['state' => 'other', 'code' => 'c']);
        $this->assertSame([], $this->sent, 'the code is not even exchanged');
    }

    public function testAMissingStateIsAnInvalidResponse(): void
    {
        $handshake = $this->begun();
        try {
            $this->provider()->complete(new Request(query: ['code' => 'c']), $handshake);
            $this->fail('the sign-in went through');
        } catch (ProviderFailure $failure) {
            $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
        }
        $this->assertFails(FailureReason::InvalidResponse, ['state' => ''], null, $this->handshake());
    }

    public function testAnErrorFromTheProviderIsRejected(): void
    {
        $h = $this->begun();
        $s = (string)$h->get('state');

        $failure = $this->assertFails(FailureReason::ProviderRejected, ['state' => $s, 'error' => 'access_denied', 'error_description' => 'The user is not allowed'], null, $h);

        $this->assertStringContainsString('access_denied', $failure->adminDetail);
        $this->assertStringContainsString('The user is not allowed', $failure->adminDetail);
        $this->assertNull($failure->employeeMessage, 'what the provider says is for the administrator');
        $this->assertSame([], $this->sent);
    }

    /** authentik's error appended after a second `?`. */
    public function testAnErrorGluedOntoTheCallbackWithASecondQuestionMarkIsTheProvidersError(): void
    {
        $h = $this->begun();
        $s = (string)$h->get('state');
        $request = Request::create(self::CALLBACK . '?error=invalid_request&error_description=Invalid%20grant_type%20for%20provider&state=' . rawurlencode($s));

        try {
            $this->provider()->complete($request, $h);
            $this->fail('the sign-in went through');
        } catch (ProviderFailure $failure) {
            $this->assertSame(FailureReason::ProviderRejected, $failure->reason, $failure->adminDetail);
            $this->assertSame('the provider answered "invalid_request": Invalid grant_type for provider', $failure->adminDetail);
        }
        $this->assertSame([], $this->sent);
    }

    /** Only the pair the wiki put there, glued: a pair naming another provider, or a code sent normally, is read as it is. */
    public function testOnlyAnErrorGluedOntoThisCallbacksPairIsRead(): void
    {
        $h = $this->begun();
        $s = (string)$h->get('state');

        $failure = $this->assertFails(FailureReason::InvalidResponse, ['state' => $s, 'code' => '', 'sso_callback' => 'other?error=invalid_request'], null, $h);
        $this->assertSame('the provider sent back no authorization code', $failure->adminDetail);

        $failure = $this->assertFails(FailureReason::ProviderRejected, ['state' => $s, 'error' => 'access_denied', 'sso_callback' => 'acme?error=invalid_request'], null, $h);
        $this->assertStringContainsString('"access_denied"', $failure->adminDetail, 'an error sent as such comes first');
    }

    public function testAMissingCodeIsAnInvalidResponse(): void
    {
        $this->assertFails(FailureReason::InvalidResponse, ['code' => '']);
    }

    public function testTheTokenEndpointErrorBodyReachesTheAdminDetail(): void
    {
        $this->answer('token', new Response(400, ['Content-Type' => 'application/json'], '{"error":"invalid_client","error_description":"Client authentication failed"}'));

        $failure = $this->assertFails(FailureReason::ProviderRejected);

        $this->assertStringContainsString('invalid_client', $failure->adminDetail);
        $this->assertStringContainsString('Client authentication failed', $failure->adminDetail);
        $this->assertStringContainsString('token', $failure->adminDetail, 'the detail says which call failed');
        $this->assertNull($failure->employeeMessage);
    }

    public function testAUserinfoErrorIsRejected(): void
    {
        $this->answer('token', self::tokenResponse());
        $this->answer('userinfo', new Response(401, ['Content-Type' => 'application/json'], '{"error":"invalid_token"}'));

        $this->assertStringContainsString('invalid_token', $this->assertFails(FailureReason::ProviderRejected)->adminDetail);
    }

    public function testANetworkFailureIsUnreachable(): void
    {
        $this->answer('token', new ConnectException('cURL error 6: Could not resolve host: idp.example', new \GuzzleHttp\Psr7\Request('POST', 'https://idp.example/token')));

        $this->assertStringContainsString('Could not resolve host', $this->assertFails(FailureReason::ProviderUnreachable)->adminDetail);
    }

    /** The provider behind a reverse proxy went down during the sign-in: the proxy answers the token or userinfo call with its gateway status, and an HTML page. */
    public function testATokenOrUserinfoCallThatTheProxyAnswersForAServerThatIsDownIsUnreachable(): void
    {
        foreach ([502, 503, 504] as $status) {
            foreach (['token', 'userinfo'] as $endpoint) {
                $this->setUp();
                $down = new Response($status, ['Content-Type' => 'text/html'], '<html><body>Bad Gateway</body></html>');
                $this->answer('token', $endpoint === 'token' ? $down : self::tokenResponse());
                if ($endpoint === 'userinfo') {
                    $this->answer('userinfo', $down);
                }

                $failure = $this->assertFails(FailureReason::ProviderUnreachable);
                $this->assertStringContainsString("the $endpoint endpoint", $failure->adminDetail, "$status $endpoint: the detail says which call failed");
                $this->assertStringContainsString("HTTP $status", $failure->adminDetail, "$status $endpoint: the detail gives the status");
            }
        }
    }

    /** Kanidm answers a wrong client secret, and an unknown access token, with a bare 401: no OAuth error in the body, no body at all. */
    public function testATokenOrUserinfoRefusalWithoutAnOAuthErrorInItsBodyIsRejected(): void
    {
        foreach ([400, 401, 403] as $status) {
            foreach (['token', 'userinfo'] as $endpoint) {
                $this->setUp();
                $refused = new Response($status, ['Content-Type' => 'text/plain; charset=utf-8'], '');
                $this->answer('token', $endpoint === 'token' ? $refused : self::tokenResponse());
                if ($endpoint === 'userinfo') {
                    $this->answer('userinfo', $refused);
                }

                $failure = $this->assertFails(FailureReason::ProviderRejected);
                $this->assertStringContainsString("the $endpoint endpoint", $failure->adminDetail, "$status $endpoint: the detail says which call failed");
                $this->assertStringContainsString("HTTP $status", $failure->adminDetail, "$status $endpoint: the detail gives the status");
            }
        }
    }

    /** An HTML page for a refusal (a proxy or WAF in front of the provider) is a refusal too. */
    public function testATokenRefusalAnsweredWithAnHtmlPageIsRejected(): void
    {
        $this->answer('token', new Response(403, ['Content-Type' => 'text/html'], '<html><body>Forbidden</body></html>'));

        $this->assertStringContainsString('the token endpoint answered HTTP 403', $this->assertFails(FailureReason::ProviderRejected)->adminDetail);
    }

    /** A token or userinfo address that does not exist is a wrong setting, as a discovery document that answers 404 is. */
    public function testATokenOrUserinfoAddressThatDoesNotExistIsAConfigurationError(): void
    {
        foreach ([404, 405, 410] as $status) {
            foreach (['token', 'userinfo'] as $endpoint) {
                $this->setUp();
                $missing = new Response($status, ['Content-Type' => 'text/html'], $status === 404 ? '' : '<html><body>Not here</body></html>');
                $this->answer('token', $endpoint === 'token' ? $missing : self::tokenResponse());
                if ($endpoint === 'userinfo') {
                    $this->answer('userinfo', $missing);
                }

                $failure = $this->assertFails(FailureReason::ConfigurationInvalid);
                $this->assertStringContainsString("the $endpoint endpoint", $failure->adminDetail, "$status $endpoint: the detail says which call failed");
                $this->assertStringContainsString("HTTP $status", $failure->adminDetail, "$status $endpoint: the detail gives the status");
            }
        }
    }

    /** Another 4xx without an OAuth error (a throttle) is neither a refusal nor a wrong address: it stays an unreadable answer. */
    public function testAThrottledTokenCallWithoutAnOAuthErrorKeepsItsClassification(): void
    {
        $this->answer('token', new Response(429, ['Content-Type' => 'text/html'], '<html><body>Too Many Requests</body></html>'));

        $this->assertFails(FailureReason::InvalidResponse);
    }

    /** A framework's own JSON error page carries no OAuth `error`: it is a refusal all the same, never the person's attributes. */
    public function testAUserinfoRefusalWithAJsonBodyWithoutAnOAuthErrorIsRejected(): void
    {
        $this->answer('token', self::tokenResponse());
        $this->answer('userinfo', new Response(401, ['Content-Type' => 'application/json'], '{"detail":"Authentication credentials were not provided."}'));

        $this->assertStringContainsString('the userinfo endpoint answered HTTP 401', $this->assertFails(FailureReason::ProviderRejected)->adminDetail);
    }

    /** An empty `error` ("" or "0") is no OAuth error: the client would take the body for the person's attributes. */
    public function testAUserinfoRefusalWithAnEmptyOAuthErrorIsRejected(): void
    {
        foreach (['""', '"0"'] as $error) {
            $this->setUp();
            $this->answer('token', self::tokenResponse());
            $this->answer('userinfo', new Response(401, ['Content-Type' => 'application/json'], '{"error":' . $error . ',"email":"x@y.example"}'));

            $this->assertStringContainsString('the userinfo endpoint answered HTTP 401', $this->assertFails(FailureReason::ProviderRejected)->adminDetail, "error $error");
        }
    }

    public function testATokenRefusalWithAJsonBodyWithoutAnOAuthErrorIsRejected(): void
    {
        $this->answer('token', new Response(401, ['Content-Type' => 'application/json'], '{"message":"Unauthorized"}'));

        $this->assertStringContainsString('the token endpoint answered HTTP 401', $this->assertFails(FailureReason::ProviderRejected)->adminDetail);
    }

    /** Spring's error page has an `error` member, but a 404 is a wrong address whatever the body says. */
    public function testATokenAddressThatDoesNotExistIsAConfigurationErrorWhateverItsJsonBody(): void
    {
        $this->answer('token', new Response(404, ['Content-Type' => 'application/json'], '{"timestamp":"2026-11-12T10:00:00.000+00:00","status":404,"error":"Not Found","path":"/oauth2/tokn"}'));

        $this->assertStringContainsString('the token endpoint answered HTTP 404', $this->assertFails(FailureReason::ConfigurationInvalid)->adminDetail);
    }

    /** Any other error status with a JSON body and no OAuth error is not an answer to read the person from. */
    public function testAnotherUserinfoErrorStatusWithAJsonBodyIsNeverTheAttributes(): void
    {
        foreach ([429, 500] as $status) {
            $this->setUp();
            $this->answer('token', self::tokenResponse());
            $this->answer('userinfo', new Response($status, ['Content-Type' => 'application/json'], '{"detail":"slow down"}'));

            $this->assertStringContainsString("the userinfo endpoint answered HTTP $status", $this->assertFails(FailureReason::InvalidResponse)->adminDetail);
        }
    }

    public function testAnAnswerThatIsNotJsonIsAnInvalidResponse(): void
    {
        $this->answer('token', new Response(200, ['Content-Type' => 'text/html'], '<html>maintenance</html>'));

        $this->assertFails(FailureReason::InvalidResponse);
    }

    public function testUserinfoBecomesTheAttributes(): void
    {
        $this->assertSame('m@x', $this->completeOk()->attributes['email']);
        $this->assertSame(['wiki-editors', 'staff'], $this->completeOk()->attributes['groups']);
        $this->assertSame('marie', $this->completeOk()->attributes['sub']);
    }

    public function testVerifiedIdTokenClaimsAreMerged(): void
    {
        $config = $this->config(self::discoveryConnection());
        $handshake = $this->begun($config);
        $this->assertStringStartsWith('https://idp.example/oauth2/authorize?', (string)$this->provider($config)->begin($this->handshake())->headers->get('Location'), 'the discovered endpoint');
        $this->answer('token', self::tokenResponse(['id_token' => self::idToken(['department' => 'R&D', 'at_hash' => 'xyz'], (string)$handshake->get('nonce'))]));
        $this->answer('jwks', self::jwksResponse());
        $this->answer('userinfo', self::userinfoResponse());

        $attributes = $this->complete($handshake, [], $config)->attributes;

        $this->assertSame('R&D', $attributes['department'], 'a claim only the token carries');
        $this->assertSame('m@x', $attributes['email'], 'and what userinfo says');
        foreach (['iss', 'aud', 'exp', 'iat', 'nonce', 'at_hash'] as $protocol) {
            $this->assertArrayNotHasKey($protocol, $attributes, 'claims about the token are not about the person');
        }
    }

    public function testAnInvalidIdTokenIsRefusedWhenItCanBeVerified(): void
    {
        $config = $this->config(self::EXPLICIT + ['issuer' => self::ISSUER, 'jwks_uri' => 'https://idp.example/jwks']);
        $handshake = $this->begun($config);
        $this->answer('token', self::tokenResponse(['id_token' => self::tampered(self::idToken([], (string)$handshake->get('nonce')))]));
        $this->answer('jwks', self::jwksResponse());
        $this->answer('userinfo', self::userinfoResponse());

        $this->assertFails(FailureReason::InvalidResponse, [], $config, $handshake);
    }

    public function testAnIdTokenForAnotherSignInIsRefused(): void
    {
        $config = $this->config(self::discoveryConnection());
        $handshake = $this->begun($config);
        $this->answer('token', self::tokenResponse(['id_token' => self::idToken([], 'the-nonce-of-another-sign-in')]));
        $this->answer('jwks', self::jwksResponse());
        $this->answer('userinfo', self::userinfoResponse());

        $this->assertStringContainsString('nonce', $this->assertFails(FailureReason::InvalidResponse, [], $config, $handshake)->adminDetail);
    }

    public function testUserinfoAboutSomeoneElseThanTheTokenIsRefused(): void
    {
        $config = $this->config(self::discoveryConnection());
        $handshake = $this->begun($config);
        $this->answer('token', self::tokenResponse(['id_token' => self::idToken([], (string)$handshake->get('nonce'))]));
        $this->answer('jwks', self::jwksResponse());
        $this->answer('userinfo', self::userinfoResponse(['sub' => 'jean']));

        $this->assertFails(FailureReason::InvalidResponse, [], $config, $handshake);
    }

    public function testAnIdTokenIsIgnoredWithExplicitEndpointsAndNoJwks(): void
    {
        $handshake = $this->begun();
        $this->answer('token', self::tokenResponse(['id_token' => self::tampered(self::idToken())]));
        $this->answer('userinfo', self::userinfoResponse());

        $attributes = $this->complete($handshake)->attributes;

        $this->assertSame('marie', $attributes['sub'], 'from userinfo, not from the token');
        $this->assertSame('m@x', $attributes['email']);
        $this->assertSame(['token', 'userinfo'], array_map(fn (RequestInterface $r): string => basename($r->getUri()->getPath()), $this->sent), 'no key set is fetched');
    }

    public function testAnIssuerWithoutKeysDoesNotMakeATokenVerifiable(): void
    {
        $config = $this->config(self::EXPLICIT + ['issuer' => self::ISSUER]);
        $handshake = $this->begun($config);
        $this->answer('token', self::tokenResponse(['id_token' => self::tampered(self::idToken())]));
        $this->answer('userinfo', self::userinfoResponse());

        $this->assertSame('m@x', $this->complete($handshake, [], $config)->attributes['email']);
    }

    public function testDiscoveryFailureIsConfigurationInvalidOrUnreachable(): void
    {
        $config = $this->config(self::discoveryConnection());
        $cases = [
            'a 404' => [new MockResponse('not found', ['http_code' => 404]), FailureReason::ConfigurationInvalid],
            'not JSON' => [new MockResponse('<html></html>'), FailureReason::ConfigurationInvalid],
            'no endpoints' => [new MockResponse('{"issuer":"https://idp.example"}'), FailureReason::ConfigurationInvalid],
            'a transport error' => [new MockResponse('', ['error' => 'Could not resolve host: idp.example']), FailureReason::ProviderUnreachable],
        ];
        foreach ($cases as $case => [$response, $reason]) {
            try {
                $this->provider($config, new MockHttpClient($response))->begin($this->handshake());
                $this->fail("$case: the sign-in left anyway");
            } catch (ProviderFailure $failure) {
                $this->assertSame($reason, $failure->reason, "$case: {$failure->adminDetail}");
                $this->assertStringContainsString(self::DISCOVERY, $failure->adminDetail, "$case: the detail names the address");
            }
        }
    }

    /** A provider behind a reverse proxy that went down between the two legs: the proxy answers the discovery with its gateway status, and no body. */
    public function testADiscoveryThatTheProxyAnswersForAServerThatIsDownIsUnreachable(): void
    {
        $config = $this->config(self::discoveryConnection());
        foreach ([502, 503, 504] as $status) {
            $handshake = $this->begun($config);
            $down = new MockHttpClient(fn (): MockResponse => new MockResponse('', ['http_code' => $status]));
            try {
                $this->provider($config, $down)->complete(new Request(query: ['state' => (string)$handshake->get('state'), 'code' => 'the-code']), $handshake);
                $this->fail("$status: the sign-in went through");
            } catch (ProviderFailure $failure) {
                $this->assertSame(FailureReason::ProviderUnreachable, $failure->reason, "$status: {$failure->adminDetail}");
                $this->assertStringContainsString(self::DISCOVERY, $failure->adminDetail, "$status: the detail names the address");
                $this->assertStringContainsString("HTTP $status", $failure->adminDetail, "$status: the detail gives the status");
            }
        }
    }

    public function testNoEndpointsAtAllIsAConfigurationError(): void
    {
        try {
            $this->provider($this->config(['client_id' => self::CLIENT_ID]))->begin($this->handshake());
            $this->fail('the sign-in left anyway');
        } catch (ProviderFailure $failure) {
            $this->assertSame(FailureReason::ConfigurationInvalid, $failure->reason);
        }
    }
}
