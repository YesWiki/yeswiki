<?php

namespace YesWiki\Test\Identity\SignIn;

use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\Response;

/** An RSA key made for the test run, the key set a provider would publish for it, and ID tokens signed with it. */
trait OidcTestKeys
{
    private const ISSUER = 'https://idp.example';
    private const CLIENT_ID = 'wiki-client';
    private const KID = 'test-key';

    private static ?string $privateKey = null;

    /** @var array<string, string>|null */
    private static ?array $publicKey = null;

    private static function generateKeys(): void
    {
        if (self::$privateKey !== null) {
            return;
        }
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key, 'openssl can make an RSA key');
        openssl_pkey_export($key, $pem);
        $details = openssl_pkey_get_details($key);
        self::$privateKey = $pem;
        self::$publicKey = ['n' => JWT::urlsafeB64Encode($details['rsa']['n']), 'e' => JWT::urlsafeB64Encode($details['rsa']['e'])];
    }

    private static function jwksResponse(): Response
    {
        self::generateKeys();

        return new Response(200, ['Content-Type' => 'application/json'], (string)json_encode(['keys' => [
            ['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => self::KID] + (self::$publicKey ?? []),
        ]]));
    }

    /** @param array<string, mixed> $claims overrides of a valid token's claims; null removes one */
    private static function idToken(array $claims = [], string $nonce = 'the-nonce'): string
    {
        self::generateKeys();
        $payload = array_filter(array_merge([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'sub' => 'marie',
            'exp' => time() + 300,
            'iat' => time(),
            'nonce' => $nonce,
        ], $claims), fn (mixed $value): bool => $value !== null);

        return JWT::encode($payload, (string)self::$privateKey, 'RS256', self::KID);
    }

    /** The same signature over claims that changed: someone else's subject. */
    private static function tampered(string $jwt): string
    {
        [$head, $body, $signature] = explode('.', $jwt);
        $body = JWT::urlsafeB64Encode((string)json_encode(['sub' => 'mallory'] + (array)json_decode(JWT::urlsafeB64Decode($body), true)));

        return "$head.$body.$signature";
    }
}
