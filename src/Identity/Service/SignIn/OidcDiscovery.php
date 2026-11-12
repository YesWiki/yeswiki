<?php

namespace YesWiki\Identity\Service\SignIn;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;

/**
 * Where an OpenID Connect or OAuth2 provider answers: read from its discovery document, or from the endpoints written in the configuration, which win over discovered ones.
 * The document is fetched each time it is needed, twice per sign-in: nothing is kept between requests.
 */
class OidcDiscovery
{
    private const TIMEOUT = 10;

    private readonly HttpClientInterface $http;

    public function __construct(?HttpClientInterface $http = null)
    {
        $this->http = $http ?? HttpClient::create();
    }

    /**
     * @return array{authorize: string, token: string, userinfo: string, issuer: ?string, jwks_uri: ?string}
     *
     * @throws ProviderFailure
     */
    public function endpoints(ProviderConfig $config): array
    {
        $url = self::text($config->connection('discovery_url'));
        $found = $url === null ? [] : $this->document($url);
        $endpoint = fn (string $key, string $discovered): ?string => self::text($config->connection($key)) ?? self::text($found[$discovered] ?? null);

        $endpoints = [
            'authorize' => $endpoint('authorize_url', 'authorization_endpoint'),
            'token' => $endpoint('token_url', 'token_endpoint'),
            'userinfo' => $endpoint('userinfo_url', 'userinfo_endpoint'),
            'issuer' => $endpoint('issuer', 'issuer'),
            'jwks_uri' => $endpoint('jwks_uri', 'jwks_uri'),
        ];
        $missing = array_keys(array_filter(['authorize' => $endpoints['authorize'], 'token' => $endpoints['token'], 'userinfo' => $endpoints['userinfo']], fn (?string $value): bool => $value === null));
        if ($missing !== [] && $url === null) {
            $settings = implode(', ', array_map(fn (string $name): string => $name . '_url', $missing));

            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "no discovery_url, and no $settings: set discovery_url, or authorize_url, token_url and userinfo_url");
        }
        if ($missing !== [] || $endpoints['authorize'] === null || $endpoints['token'] === null || $endpoints['userinfo'] === null) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "the discovery document at $url names no " . implode(', ', $missing) . ' endpoint');
        }

        return [
            'authorize' => $endpoints['authorize'],
            'token' => $endpoints['token'],
            'userinfo' => $endpoints['userinfo'],
            'issuer' => $endpoints['issuer'],
            'jwks_uri' => $endpoints['jwks_uri'],
        ];
    }

    /** @return array<string, mixed> */
    private function document(string $url): array
    {
        try {
            $response = $this->http->request('GET', $url, ['headers' => ['Accept' => 'application/json'], 'timeout' => self::TIMEOUT]);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $unreachable) {
            throw new ProviderFailure(FailureReason::ProviderUnreachable, "the discovery document at $url could not be fetched: " . $unreachable->getMessage());
        }
        if ($status !== 200) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "the discovery document at $url answered HTTP $status");
        }
        $document = json_decode($body, true);
        if (!is_array($document)) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "the discovery document at $url is not JSON");
        }

        return $document;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
