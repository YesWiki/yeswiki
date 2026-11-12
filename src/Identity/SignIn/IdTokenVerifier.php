<?php

namespace YesWiki\Identity\SignIn;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;

/**
 * Checks an OpenID Connect ID token: its signature against the provider's published keys, then its issuer, audience, lifetime and nonce.
 * php-jwt reads its clock tolerance from a static, which is left alone: the tolerance is applied here, to the payload its exceptions carry once the signature is good.
 */
final class IdTokenVerifier
{
    /** How far the provider's clock may be from ours, in seconds. */
    private const LEEWAY = 60;

    /** Only signatures made with a private key: a shared-secret algorithm would turn the public key into the secret. */
    private const ALGORITHMS = ['RS256', 'RS384', 'RS512', 'PS256', 'PS384', 'PS512', 'ES256', 'ES384', 'ES512', 'EdDSA'];

    private const TIMEOUT = 10;

    public function __construct(private readonly ClientInterface $http)
    {
    }

    /**
     * @return array<string, mixed> the token's claims
     *
     * @throws ProviderFailure
     */
    public function verify(#[\SensitiveParameter] string $jwt, string $jwksUri, string $issuer, string $audience, string $nonce): array
    {
        $alg = $this->algorithm($jwt);
        $claims = $this->decode($jwt, $this->keys($jwksUri, $alg));

        if (($claims['iss'] ?? null) !== $issuer) {
            throw self::invalid(sprintf('the ID token has the issuer "%s", "%s" was expected', self::show($claims['iss'] ?? null), $issuer));
        }
        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];
        if (!in_array($audience, $audiences, true)) {
            throw self::invalid(sprintf('the ID token is meant for the audience "%s", not for the client "%s"', implode(', ', array_map([self::class, 'show'], $audiences)), $audience));
        }
        if (isset($claims['azp']) && $claims['azp'] !== $audience) {
            throw self::invalid(sprintf('the ID token was issued to the party "%s", not to the client "%s"', self::show($claims['azp']), $audience));
        }
        if (!is_numeric($claims['exp'] ?? null)) {
            throw self::invalid('the ID token has no expiry');
        }
        $sent = $claims['nonce'] ?? null;
        if ($nonce === '' || !is_string($sent) || !hash_equals($nonce, $sent)) {
            throw self::invalid('the ID token\'s nonce is not the one this sign-in sent: it belongs to another sign-in');
        }

        return $claims;
    }

    private function algorithm(string $jwt): string
    {
        $parts = explode('.', $jwt);
        $header = count($parts) === 3 ? json_decode(JWT::urlsafeB64Decode($parts[0]), true) : null;
        $alg = is_array($header) ? ($header['alg'] ?? null) : null;
        if (!is_string($alg) || !in_array($alg, self::ALGORITHMS, true)) {
            throw self::invalid(is_string($alg) ? "the ID token is signed with $alg, which is not accepted" : 'the ID token cannot be read');
        }

        return $alg;
    }

    /** @return array<string, \Firebase\JWT\Key> */
    private function keys(string $jwksUri, string $alg): array
    {
        try {
            $response = $this->http->send(new Request('GET', $jwksUri, ['Accept' => 'application/json']), ['timeout' => self::TIMEOUT]);
        } catch (BadResponseException $missing) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "the provider's keys at $jwksUri answered HTTP " . $missing->getResponse()->getStatusCode());
        } catch (GuzzleException $unreachable) {
            throw new ProviderFailure(FailureReason::ProviderUnreachable, "the provider's keys at $jwksUri could not be fetched: " . $unreachable->getMessage());
        }
        $set = json_decode((string)$response->getBody(), true);
        try {
            return JWK::parseKeySet(is_array($set) ? $set : [], $alg);
        } catch (\Throwable $unreadable) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "the provider's keys at $jwksUri cannot be read: " . $unreadable->getMessage());
        }
    }

    /**
     * @param array<string, \Firebase\JWT\Key> $keys
     *
     * @return array<string, mixed>
     */
    private function decode(string $jwt, array $keys): array
    {
        try {
            $payload = JWT::decode($jwt, $keys);
        } catch (BeforeValidException|ExpiredException $timing) {
            // php-jwt checks the times after the signature: the payload it carries is signed.
            $payload = $timing->getPayload();
        } catch (\Throwable $refused) {
            throw self::invalid('the ID token was refused: ' . $refused->getMessage());
        }

        $claims = json_decode((string)json_encode($payload), true);
        $claims = is_array($claims) ? $claims : [];
        $now = time();
        $from = $claims['nbf'] ?? $claims['iat'] ?? null;
        if (is_numeric($from) && floor((float)$from) > $now + self::LEEWAY) {
            throw self::invalid('the ID token is not valid yet: check the server clocks');
        }
        if (is_numeric($claims['exp'] ?? null) && $now - self::LEEWAY >= (float)$claims['exp']) {
            throw self::invalid('the ID token has expired');
        }

        return $claims;
    }

    private static function invalid(string $detail): ProviderFailure
    {
        return new ProviderFailure(FailureReason::InvalidResponse, $detail);
    }

    private static function show(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : (string)json_encode($value);
    }
}
