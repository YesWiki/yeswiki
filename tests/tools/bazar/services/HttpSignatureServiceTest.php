<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Bazar\Service\HttpSignatureService;
use YesWiki\Bazar\Service\SsrfUrlValidator;

require_once 'includes/autoload.inc.php';
require_once 'includes/constants.php';

class HttpSignatureServiceTest extends TestCase
{
    private const ACTOR = 'https://them.example/actors/1';
    private const BODY = '{"type":"Delete","actor":"https://them.example/actors/1","object":"https://them.example/entries/42"}';

    private static ?array $keyPair = null;
    private string $seenDir;

    protected function setUp(): void
    {
        $this->seenDir = sys_get_temp_dir() . '/yeswiki-seen-signatures-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->seenDir . '/*') ?: []);
        @rmdir($this->seenDir);
    }

    private function keyPair(): array
    {
        if (self::$keyPair === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            self::$keyPair = [$private, openssl_pkey_get_details($key)['key']];
        }

        return self::$keyPair;
    }

    private function verifier(int $now): HttpSignatureService
    {
        $service = new class($this->createStub(SsrfUrlValidator::class), $this->seenDir) extends HttpSignatureService {
            public int $clock = 0;

            public function useClient($client): void
            {
                $this->httpClient = $client;
            }

            protected function now(): int
            {
                return $this->clock;
            }
        };
        $service->clock = $now;
        $actor = json_encode([
            'id' => self::ACTOR,
            'publicKey' => ['id' => self::ACTOR . '#main-key', 'owner' => self::ACTOR, 'publicKeyPem' => $this->keyPair()[1]],
        ]);
        $service->useClient(new MockHttpClient(fn () => new MockResponse($actor)));

        return $service;
    }

    private function signedRequest(int $date, string $signedHeaders = '(request-target) date digest', string $algorithm = 'rsa-sha256', ?string $target = null): Request
    {
        $headers = [
            'host' => 'wiki.example',
            'date' => gmdate('D, d M Y H:i:s \\G\\M\\T', $date),
            'digest' => 'SHA-256=' . base64_encode(hash('sha256', self::BODY, true)),
        ];
        $request = Request::create('https://wiki.example/index.php', 'POST', [], [], [], [
            'HTTP_DATE' => $headers['date'],
            'HTTP_DIGEST' => $headers['digest'],
        ], self::BODY);
        $lines = [];
        foreach (explode(' ', $signedHeaders) as $header) {
            $lines[] = $header === '(request-target)'
                ? '(request-target): post ' . ($target ?? $request->getScriptName())
                : "{$header}: {$headers[$header]}";
        }
        openssl_sign(implode("\n", $lines), $signature, $this->keyPair()[0], OPENSSL_ALGO_SHA256);
        $request->headers->set('Signature', sprintf(
            'keyId="%s#main-key",algorithm="%s",headers="%s",signature="%s"',
            self::ACTOR,
            $algorithm,
            $signedHeaders,
            base64_encode($signature)
        ));

        return $request;
    }

    public function testTheSignatureIsCheckedAgainstThePublishedInboxPathNotTheRewrittenOne()
    {
        $now = time();
        $request = $this->signedRequest($now, '(request-target) date digest', 'rsa-sha256', '/actors/1/inbox');

        $this->assertSame(self::ACTOR, $this->verifier($now)->verifySignature($request, null, '/actors/1/inbox'));
    }

    public function testAFreshSignedRequestIsAccepted()
    {
        $now = time();

        $this->assertSame(self::ACTOR, $this->verifier($now)->verifySignature($this->signedRequest($now - 60)));
    }

    public function testAGoToSocialHs2019SignatureIsAccepted()
    {
        $now = time();

        $this->assertSame(self::ACTOR, $this->verifier($now)->verifySignature($this->signedRequest($now, '(request-target) host date digest', 'hs2019')));
    }

    public function testASignatureWithoutAlgorithmIsReadAsHs2019()
    {
        $now = time();
        $request = $this->signedRequest($now);
        $request->headers->set('Signature', preg_replace('/algorithm="[^"]*",\s*/', '', $request->headers->get('Signature')));

        $this->assertSame(self::ACTOR, $this->verifier($now)->verifySignature($request));
    }

    public function testSpacesBetweenSignatureParametersAreAllowed()
    {
        $now = time();
        $request = $this->signedRequest($now);
        $request->headers->set('Signature', str_replace('",', '", ', $request->headers->get('Signature')));

        $this->assertSame(self::ACTOR, $this->verifier($now)->verifySignature($request));
    }

    public function testAnRfc9421SignatureIsRefusedPlainly()
    {
        $now = time();
        $request = $this->signedRequest($now);
        $request->headers->set('Signature-Input', 'sig1=("@method" "@target-uri" "content-digest");created=' . $now . ';keyid="' . self::ACTOR . '#main-key"');
        $request->headers->set('Signature', 'sig1=:' . base64_encode(random_bytes(256)) . ':');

        $this->expectExceptionMessage('RFC 9421 signatures are not supported');
        $this->verifier($now)->verifySignature($request);
    }

    #[DataProvider('unreadableSignatures')]
    public function testAnUnreadableSignatureIsRefusedAsMalformed(string $signature)
    {
        $request = $this->signedRequest(time());
        $request->headers->set('Signature', $signature);

        $this->expectExceptionMessage('Malformed signature');
        $this->verifier(time())->verifySignature($request);
    }

    public static function unreadableSignatures(): array
    {
        return [
            'base64 without quotes' => ['sig1=:YWJj==:'],
            'unquoted value' => ['keyId=https://them.example/actors/1#main-key,headers="date",signature="YWJj"'],
            'no keyId' => ['headers="date",signature="YWJj"'],
        ];
    }

    public function testAnUnknownAlgorithmIsRefused()
    {
        $now = time();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported signature algorithm');

        $this->verifier($now)->verifySignature($this->signedRequest($now, '(request-target) date digest', 'hmac-sha256'));
    }

    public function testTheKeyIsFetchedWithASignedRequestWhenTheFormCanSign()
    {
        $now = time();
        $sent = [];
        $verifier = $this->verifier($now);
        $actor = json_encode([
            'id' => self::ACTOR,
            'publicKey' => ['id' => self::ACTOR . '#main-key', 'owner' => self::ACTOR, 'publicKeyPem' => $this->keyPair()[1]],
        ]);
        $verifier->useClient(new MockHttpClient(function ($method, $url, $options) use (&$sent, $actor) {
            $sent = $options['normalized_headers'];

            return new MockResponse($actor);
        }));

        $verifier->verifySignature($this->signedRequest($now), ['keyId' => 'https://us.example/actors/1#main-key', 'privateKey' => $this->keyPair()[0]]);

        $this->assertStringContainsString('keyId="https://us.example/actors/1#main-key"', $sent['signature'][0]);
        $this->assertStringContainsString('headers="(request-target) host date"', $sent['signature'][0]);
        $this->assertStringContainsString('application/activity+json', $sent['accept'][0]);
    }

    public function testASignedPostVerifiesWithTheSignerKey()
    {
        $now = time();
        $headers = $this->verifier($now)->signHeaders('POST', 'https://wiki.example/index.php', ['keyId' => self::ACTOR . '#main-key', 'privateKey' => $this->keyPair()[0]], self::BODY);
        $server = ['SCRIPT_NAME' => '/index.php', 'CONTENT_TYPE' => $headers['Content-Type']];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $request = Request::create('https://wiki.example/index.php', 'POST', [], [], [], $server, self::BODY);

        $this->assertSame(self::ACTOR, $this->verifier($now)->verifySignature($request));
    }

    public function testTheSameRequestCannotBeReplayed()
    {
        $now = time();
        $request = $this->signedRequest($now);
        $this->verifier($now)->verifySignature($request);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already been received');

        $this->verifier($now + 30)->verifySignature($request);
    }

    #[DataProvider('staleProvider')]
    public function testARequestDatedTooFarFromNowIsRefused(int $offset)
    {
        $now = time();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('dated too far from now');

        $this->verifier($now)->verifySignature($this->signedRequest($now + $offset));
    }

    public static function staleProvider(): array
    {
        return [
            'captured yesterday' => [-86400],
            'just past the window' => [-HttpSignatureService::MAX_CLOCK_SKEW - 1],
            'from the future' => [HttpSignatureService::MAX_CLOCK_SKEW + 1],
        ];
    }

    #[DataProvider('unsignedHeaderProvider')]
    public function testARequestWhoseDateOrBodyIsNotSignedIsRefused(string $signedHeaders, string $missing)
    {
        $now = time();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("does not cover the {$missing} header");

        $this->verifier($now)->verifySignature($this->signedRequest($now, $signedHeaders));
    }

    public static function unsignedHeaderProvider(): array
    {
        return [
            'no date' => ['(request-target) digest', 'date'],
            'no digest' => ['(request-target) date', 'digest'],
        ];
    }

    private function service(): HttpSignatureService
    {
        return new class($this->createStub(SsrfUrlValidator::class)) extends HttpSignatureService {
            public function owner(string $keyId, array $actor): string
            {
                return $this->keyOwner($keyId, $actor);
            }
        };
    }

    public function testTheKeyOwnerIsWhoTheDocumentSaysItIs()
    {
        $owner = $this->service()->owner('https://them.example/actors/1#main-key', [
            'id' => 'https://them.example/actors/1',
            'publicKey' => ['owner' => 'https://them.example/actors/1'],
        ]);

        $this->assertSame('https://them.example/actors/1', $owner);
    }

    public function testADocumentThatNamesNoOwnerIsRefused()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('names no owner');

        $this->service()->owner('https://them.example/actors/1#main-key', ['publicKey' => []]);
    }

    public function testAKeyCannotClaimAnActorOnAnotherHost()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not on the same host');

        $this->service()->owner('https://attacker.example/key', [
            'id' => 'https://them.example/actors/1',
            'publicKey' => ['owner' => 'https://them.example/actors/1'],
        ]);
    }

    public function testADocumentThatDisagreesWithItsOwnKeyIsRefused()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('disagree on who owns the key');

        $this->service()->owner('https://them.example/key', [
            'id' => 'https://them.example/actors/1',
            'publicKey' => ['owner' => 'https://them.example/actors/2'],
        ]);
    }

    #[DataProvider('hostProvider')]
    public function testSameHost(string $first, string $second, bool $expected)
    {
        $this->assertSame($expected, $this->service()->sameHost($first, $second));
    }

    public static function hostProvider(): array
    {
        return [
            'the same host' => ['https://a.example/one', 'https://a.example/two', true],
            'a different host' => ['https://a.example/one', 'https://b.example/one', false],
            'a subdomain is another host' => ['https://a.example/one', 'https://x.a.example/one', false],
            'the case of the host does not matter' => ['https://A.Example/one', 'https://a.example/one', true],
            'a different scheme' => ['https://a.example/one', 'http://a.example/one', false],
            'a different port' => ['https://a.example:8443/one', 'https://a.example/one', false],
            'the host as a userinfo' => ['https://a.example@attacker.example/x', 'https://a.example/x', false],
            'nothing that parses' => ['not a url', 'https://a.example/x', false],
        ];
    }
}
