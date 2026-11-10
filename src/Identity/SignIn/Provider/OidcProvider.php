<?php

namespace YesWiki\Identity\SignIn\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Identity\Service\SignIn\OidcDiscovery;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\Contract\RedirectProvider;
use YesWiki\Identity\SignIn\IdTokenVerifier;

/**
 * OpenID Connect, or plain OAuth2 when only endpoints are configured (what Doryphore's loginsso held): authorization code with state, PKCE S256 and a nonce.
 * The attributes come from userinfo. The ID token's claims are added when the token can be checked, with discovery or with `issuer` and `jwks_uri`; otherwise the token is ignored.
 * The league client keeps the state and the verifier of one trip, so one is made per call and nothing outlives it.
 */
final class OidcProvider implements RedirectProvider
{
    private const DEFAULT_SCOPES = ['openid', 'email', 'profile'];
    private const TIMEOUT = 10;

    /** Claims about the token, not about the person: kept out of the attributes, and out of the Journal with them. */
    private const PROTOCOL_CLAIMS = ['iss', 'aud', 'exp', 'iat', 'nbf', 'nonce', 'at_hash', 'c_hash', 's_hash', 'auth_time', 'azp', 'jti', 'sid', 'acr', 'amr'];

    private readonly ClientInterface $http;

    public function __construct(
        private readonly ProviderConfig $config,
        private readonly OidcDiscovery $discovery,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client(['timeout' => self::TIMEOUT, 'connect_timeout' => self::TIMEOUT]);
    }

    public function id(): string
    {
        return $this->config->id;
    }

    public function label(): string
    {
        return $this->config->label;
    }

    public function type(): string
    {
        return $this->config->type;
    }

    public function begin(Handshake $handshake): Response
    {
        $client = $this->client($this->discovery->endpoints($this->config), $handshake->callbackUrl);
        $nonce = bin2hex(random_bytes(16));
        $url = $client->getAuthorizationUrl(['nonce' => $nonce]);
        $handshake->set('state', $client->getState());
        $handshake->set('nonce', $nonce);
        $handshake->set('pkce', (string)$client->getPkceCode());

        return new RedirectResponse($url);
    }

    public function complete(Request $request, Handshake $handshake): ProviderIdentity
    {
        $answer = $request->query->all() + $request->request->all();
        $state = $answer['state'] ?? null;
        $expected = (string)$handshake->get('state');
        if ($expected === '' || !is_string($state) || !hash_equals($expected, $state)) {
            throw new ProviderFailure(FailureReason::InvalidResponse, 'the state sent back is not the one this sign-in started with');
        }
        if (isset($answer['error'])) {
            $description = is_string($answer['error_description'] ?? null) ? ': ' . $answer['error_description'] : '';

            throw new ProviderFailure(FailureReason::ProviderRejected, 'the provider answered "' . self::show($answer['error']) . '"' . $description);
        }
        $code = $answer['code'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new ProviderFailure(FailureReason::InvalidResponse, 'the provider sent back no authorization code');
        }

        $endpoints = $this->discovery->endpoints($this->config);
        $client = $this->client($endpoints, $handshake->callbackUrl);
        $client->setPkceCode((string)$handshake->get('pkce'));
        $token = $this->call('token', fn (): AccessToken => $client->getAccessToken('authorization_code', ['code' => $code]));
        $claims = $this->verifiedClaims($token, $endpoints, (string)$handshake->get('nonce'));
        $userinfo = $this->call('userinfo', fn (): array => $client->getResourceOwner($token)->toArray());

        if (isset($claims['sub'], $userinfo['sub']) && $claims['sub'] !== $userinfo['sub']) {
            throw new ProviderFailure(FailureReason::InvalidResponse, sprintf('userinfo is about "%s", the ID token about "%s"', self::show($userinfo['sub']), self::show($claims['sub'])));
        }

        return new ProviderIdentity($userinfo + array_diff_key($claims, array_flip(self::PROTOCOL_CLAIMS)));
    }

    /** @param array{authorize: string, token: string, userinfo: string, issuer: ?string, jwks_uri: ?string} $endpoints */
    private function client(array $endpoints, string $redirectUri): GenericProvider
    {
        $separator = $this->config->connection('scope_separator');

        return new GenericProvider([
            'clientId' => (string)$this->config->connection('client_id', ''),
            'clientSecret' => (string)$this->config->connection('client_secret', ''),
            'redirectUri' => $redirectUri,
            'urlAuthorize' => $endpoints['authorize'],
            'urlAccessToken' => $endpoints['token'],
            'urlResourceOwnerDetails' => $endpoints['userinfo'],
            'scopes' => $this->scopes(),
            'scopeSeparator' => is_string($separator) && $separator !== '' ? $separator : ' ',
            'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
        ], ['httpClient' => $this->http]);
    }

    /** @return list<string> */
    private function scopes(): array
    {
        $scopes = $this->config->connection('scopes');
        if (is_string($scopes)) {
            $scopes = preg_split('/[\s,]+/', $scopes, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        $scopes = is_array($scopes) ? array_values(array_filter(array_map('strval', $scopes), fn (string $scope): bool => $scope !== '')) : [];

        return $scopes === [] ? self::DEFAULT_SCOPES : $scopes;
    }

    /**
     * @param array{authorize: string, token: string, userinfo: string, issuer: ?string, jwks_uri: ?string} $endpoints
     *
     * @return array<string, mixed>
     */
    private function verifiedClaims(AccessToken $token, array $endpoints, string $nonce): array
    {
        $idToken = $token->getValues()['id_token'] ?? null;
        if ($endpoints['issuer'] === null || $endpoints['jwks_uri'] === null || !is_string($idToken) || $idToken === '') {
            return [];
        }

        return (new IdTokenVerifier($this->http))->verify($idToken, $endpoints['jwks_uri'], $endpoints['issuer'], (string)$this->config->connection('client_id', ''), $nonce);
    }

    /**
     * A call to the provider, its failures said for an administrator: what the provider answered, never what was sent.
     *
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    private function call(string $endpoint, callable $call): mixed
    {
        try {
            return $call();
        } catch (IdentityProviderException $refused) {
            throw new ProviderFailure(FailureReason::ProviderRejected, "the $endpoint endpoint refused: " . self::show($refused->getResponseBody()));
        } catch (GuzzleException $unreachable) {
            throw new ProviderFailure(FailureReason::ProviderUnreachable, "the $endpoint endpoint could not be reached: " . $unreachable->getMessage());
        } catch (\UnexpectedValueException $unreadable) {
            throw new ProviderFailure(FailureReason::InvalidResponse, "the $endpoint endpoint answered something unreadable: " . $unreadable->getMessage());
        }
    }

    private static function show(mixed $value): string
    {
        if (is_object($value) && method_exists($value, 'getBody')) {
            $value = (string)$value->getBody();
        }

        return is_scalar($value) ? (string)$value : (string)json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
