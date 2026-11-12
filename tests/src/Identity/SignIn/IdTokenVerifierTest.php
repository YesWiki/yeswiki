<?php

namespace YesWiki\Test\Identity\SignIn;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\IdTokenVerifier;

require_once __DIR__ . '/OidcTestKeys.php';

class IdTokenVerifierTest extends TestCase
{
    use OidcTestKeys;

    private const JWKS = 'https://idp.example/jwks';

    /** @param list<Response|\Throwable> $answers what the key set address answers, in turn */
    private function verifier(array $answers = []): IdTokenVerifier
    {
        $answers = $answers === [] ? [self::jwksResponse()] : $answers;

        return new IdTokenVerifier(new Client(['handler' => HandlerStack::create(new MockHandler($answers))]));
    }

    /** @return array<string, mixed> */
    private function verify(string $jwt, string $nonce = 'the-nonce', ?IdTokenVerifier $verifier = null): array
    {
        return ($verifier ?? $this->verifier())->verify($jwt, self::JWKS, self::ISSUER, self::CLIENT_ID, $nonce);
    }

    private function assertRefused(string $jwt, string $nonce = 'the-nonce', FailureReason $reason = FailureReason::InvalidResponse, ?IdTokenVerifier $verifier = null): ProviderFailure
    {
        try {
            $this->verify($jwt, $nonce, $verifier);
        } catch (ProviderFailure $failure) {
            $this->assertSame($reason, $failure->reason, $failure->adminDetail);
            $this->assertStringNotContainsString($jwt, $failure->adminDetail, 'a token never reaches the detail');

            return $failure;
        }
        $this->fail('the token was accepted');
    }

    public function testValidToken(): void
    {
        $claims = $this->verify(self::idToken(['email' => 'marie@acme.example']));

        $this->assertSame('marie', $claims['sub']);
        $this->assertSame('marie@acme.example', $claims['email']);
    }

    public function testAnAudienceListNamingTheClientIsValid(): void
    {
        $this->assertSame('marie', $this->verify(self::idToken(['aud' => ['other', self::CLIENT_ID], 'azp' => self::CLIENT_ID]))['sub']);
    }

    public function testAClockAFewSecondsAheadIsTolerated(): void
    {
        $this->assertSame('marie', $this->verify(self::idToken(['iat' => time() + 20]))['sub']);
    }

    public function testWrongAudience(): void
    {
        $this->assertStringContainsString('audience', $this->assertRefused(self::idToken(['aud' => 'another-client']))->adminDetail);
    }

    public function testAnotherPartyAuthorizedIsRefused(): void
    {
        $this->assertRefused(self::idToken(['aud' => [self::CLIENT_ID, 'other'], 'azp' => 'other']));
    }

    public function testWrongIssuer(): void
    {
        $this->assertStringContainsString('issuer', $this->assertRefused(self::idToken(['iss' => 'https://evil.example']))->adminDetail);
    }

    public function testExpired(): void
    {
        $this->assertStringContainsString('expired', $this->assertRefused(self::idToken(['exp' => time() - 3600, 'iat' => time() - 7200]))->adminDetail);
    }

    public function testATokenWithoutExpiryIsRefused(): void
    {
        $this->assertRefused(self::idToken(['exp' => null]));
    }

    public function testATokenIssuedInTheFutureIsRefused(): void
    {
        $this->assertRefused(self::idToken(['iat' => time() + 3600]));
    }

    public function testWrongNonce(): void
    {
        $this->assertStringContainsString('nonce', $this->assertRefused(self::idToken(), 'another-nonce')->adminDetail);
    }

    public function testATokenWithoutNonceIsRefused(): void
    {
        $this->assertRefused(self::idToken(['nonce' => null]));
    }

    public function testWrongSignature(): void
    {
        $this->assertStringContainsStringIgnoringCase('signature', $this->assertRefused(self::tampered(self::idToken()))->adminDetail);
    }

    public function testAnUnsignedTokenIsRefused(): void
    {
        [$head, $body] = explode('.', self::idToken());
        $none = rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=');

        $this->assertRefused("$none.$body.");
        $this->assertRefused("$head.$body");
    }

    public function testASymmetricAlgorithmIsRefused(): void
    {
        [, $body, $signature] = explode('.', self::idToken());
        $hs256 = rtrim(strtr(base64_encode('{"alg":"HS256","typ":"JWT","kid":"test-key"}'), '+/', '-_'), '=');

        $this->assertRefused("$hs256.$body.$signature");
    }

    public function testUnreachableKeysAreUnreachable(): void
    {
        $verifier = $this->verifier([new ConnectException('Could not resolve host', new Request('GET', self::JWKS))]);

        $this->assertRefused(self::idToken(), 'the-nonce', FailureReason::ProviderUnreachable, $verifier);
    }

    public function testAMissingKeySetIsAConfigurationError(): void
    {
        $this->assertRefused(self::idToken(), 'the-nonce', FailureReason::ConfigurationInvalid, $this->verifier([new Response(404, [], 'not here')]));
    }
}
