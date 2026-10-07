<?php

namespace YesWiki\Test\Federation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Federation\Service\HttpSignatureService;
use YesWiki\Federation\Service\SeenSignatures;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\SsrfUrlValidator;

require_once 'tests/YesWikiTestCase.php';

class HttpSignatureServiceTest extends TestCase
{
    private const ACTOR = 'https://them.example/actors/1';
    private const BODY = '{"type":"Delete","actor":"https://them.example/actors/1","object":"https://them.example/entries/42"}';

    /** @var array{0: string, 1: string}|null */
    private static ?array $keyPair = null;
    private string $database;

    protected function setUp(): void
    {
        $this->database = sys_get_temp_dir() . '/yeswiki-seen-signatures-' . bin2hex(random_bytes(4)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->database . $suffix);
        }
    }

    private function connection(): DbService
    {
        $dbService = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->database,
            'table_prefix' => 'ywsig_',
            'debug' => false,
        ]));
        $dbService->query('CREATE TABLE IF NOT EXISTS ywsig_triples (id INTEGER PRIMARY KEY AUTOINCREMENT, resource TEXT NOT NULL, property TEXT NOT NULL, value TEXT NOT NULL)');

        return $dbService;
    }

    /** A store on its own database connection. */
    private function store(): SeenSignatures
    {
        return new SeenSignatures($this->connection());
    }

    /** @return array{0: string, 1: string} the private and the public key, in PEM */
    private function keyPair(): array
    {
        if (self::$keyPair === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $this->assertNotFalse($key);
            openssl_pkey_export($key, $private);
            $details = openssl_pkey_get_details($key);
            if ($details === false) {
                $this->fail('the key pair has no public half');
            }
            self::$keyPair = [(string)$private, (string)$details['key']];
        }

        return self::$keyPair;
    }

    private function verifier(int $now): ClockedHttpSignatureService
    {
        $service = new ClockedHttpSignatureService($this->createStub(SsrfUrlValidator::class), $this->store());
        $service->clock = $now;
        $actor = (string)json_encode([
            'id' => self::ACTOR,
            'publicKey' => ['id' => self::ACTOR . '#main-key', 'owner' => self::ACTOR, 'publicKeyPem' => $this->keyPair()[1]],
        ]);
        $service->useClient(new MockHttpClient(fn () => new MockResponse($actor)));

        return $service;
    }

    private function signedRequest(int $date, string $signedHeaders = '(request-target) date digest'): Request
    {
        $headers = [
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
                ? "(request-target): post {$request->getScriptName()}"
                : "{$header}: {$headers[$header]}";
        }
        openssl_sign(implode("\n", $lines), $signature, $this->keyPair()[0], OPENSSL_ALGO_SHA256);
        $request->headers->set('Signature', sprintf(
            'keyId="%s#main-key",algorithm="rsa-sha256",headers="%s",signature="%s"',
            self::ACTOR,
            $signedHeaders,
            base64_encode((string)$signature)
        ));

        return $request;
    }

    public function testAFreshSignedRequestIsAccepted(): void
    {
        $now = time();

        $this->assertSame(self::ACTOR, $this->verifier($now)->verifySignature($this->signedRequest($now - 60)));
    }

    public function testTheSameRequestCannotBeReplayed(): void
    {
        $now = time();
        $request = $this->signedRequest($now);
        $this->verifier($now)->verifySignature($request);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already been received');

        $this->verifier($now + 30)->verifySignature($request);
    }

    public function testTwoConnectionsCannotBothAcceptTheSameSignature(): void
    {
        $now = time();
        $first = $this->store();
        $second = $this->store();

        $this->assertTrue($first->remember('sig', $now, $now - 7200));
        $this->assertFalse($second->remember('sig', $now, $now - 7200));
        $this->assertTrue($second->remember('another sig', $now, $now - 7200));
    }

    public function testWhenTwoRequestsRaceTheOldestRowWins(): void
    {
        $now = time();
        $dbService = $this->connection();
        $resource = SeenSignatures::RESOURCE_PREFIX . hash('sha256', 'sig');
        $dbService->query(
            'INSERT INTO ywsig_triples (resource, property, value) VALUES (?, ?, ?)',
            [$resource, SeenSignatures::PROPERTY, sprintf('%020d', $now) . '|someone-else']
        );

        $this->assertFalse($this->store()->remember('sig', $now, $now - 7200));
        $this->assertSame(1, $dbService->countRows('SELECT id FROM ywsig_triples WHERE resource = ?', [$resource]));
    }

    public function testOldSignaturesArePurgedAndOtherTriplesStay(): void
    {
        $now = time();
        $dbService = $this->connection();
        $dbService->query(
            'INSERT INTO ywsig_triples (resource, property, value) VALUES (?, ?, ?)',
            ['SomePage', 'http://outils-reseaux.org/_vocabulary/type', '0']
        );
        $store = $this->store();
        $store->remember('old', $now - 9000, $now - 16200);

        $this->assertTrue($store->remember('new', $now, $now - 7200));
        $this->assertTrue($store->remember('old', $now, $now - 7200));
        $this->assertSame(2, $dbService->countRows('SELECT id FROM ywsig_triples WHERE property = ?', [SeenSignatures::PROPERTY]));
        $this->assertSame(1, $dbService->countRows('SELECT id FROM ywsig_triples WHERE resource = ?', ['SomePage']));
    }

    public function testASignatureIsForgottenOnceTwiceTheClockSkewHasPassed(): void
    {
        $now = time();
        $request = $this->signedRequest($now);
        $this->verifier($now)->verifySignature($request);
        $later = $now + 2 * HttpSignatureService::MAX_CLOCK_SKEW + 1;

        $this->assertTrue($this->store()->remember((string)$this->signatureOf($request), $later, $later - 2 * HttpSignatureService::MAX_CLOCK_SKEW));
    }

    private function signatureOf(Request $request): ?string
    {
        preg_match('/signature="([^"]+)"/', (string)$request->headers->get('Signature'), $matches);

        return $matches[1] ?? null;
    }

    #[DataProvider('staleProvider')]
    public function testARequestDatedTooFarFromNowIsRefused(int $offset): void
    {
        $now = time();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('dated too far from now');

        $this->verifier($now)->verifySignature($this->signedRequest($now + $offset));
    }

    /** @return array<string, array{0: int}> */
    public static function staleProvider(): array
    {
        return [
            'captured yesterday' => [-86400],
            'just past the window' => [-HttpSignatureService::MAX_CLOCK_SKEW - 1],
            'from the future' => [HttpSignatureService::MAX_CLOCK_SKEW + 1],
        ];
    }

    #[DataProvider('unsignedHeaderProvider')]
    public function testARequestWhoseDateOrBodyIsNotSignedIsRefused(string $signedHeaders, string $missing): void
    {
        $now = time();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("does not cover the {$missing} header");

        $this->verifier($now)->verifySignature($this->signedRequest($now, $signedHeaders));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function unsignedHeaderProvider(): array
    {
        return [
            'no date' => ['(request-target) digest', 'date'],
            'no digest' => ['(request-target) date', 'digest'],
        ];
    }

    private function service(): OpenedHttpSignatureService
    {
        return new OpenedHttpSignatureService($this->createStub(SsrfUrlValidator::class));
    }

    public function testTheKeyOwnerIsWhoTheDocumentSaysItIs(): void
    {
        $owner = $this->service()->owner('https://them.example/actors/1#main-key', [
            'id' => 'https://them.example/actors/1',
            'publicKey' => ['owner' => 'https://them.example/actors/1'],
        ]);

        $this->assertSame('https://them.example/actors/1', $owner);
    }

    public function testADocumentThatNamesNoOwnerIsRefused(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('names no owner');

        $this->service()->owner('https://them.example/actors/1#main-key', ['publicKey' => []]);
    }

    public function testAKeyCannotClaimAnActorOnAnotherHost(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not on the same host');

        $this->service()->owner('https://attacker.example/key', [
            'id' => 'https://them.example/actors/1',
            'publicKey' => ['owner' => 'https://them.example/actors/1'],
        ]);
    }

    public function testADocumentThatDisagreesWithItsOwnKeyIsRefused(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('disagree on who owns the key');

        $this->service()->owner('https://them.example/key', [
            'id' => 'https://them.example/actors/1',
            'publicKey' => ['owner' => 'https://them.example/actors/2'],
        ]);
    }

    #[DataProvider('hostProvider')]
    public function testSameHost(string $first, string $second, bool $expected): void
    {
        $this->assertSame($expected, $this->service()->sameHost($first, $second));
    }

    /** @return array<string, array{0: string, 1: string, 2: bool}> */
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

/** keyOwner() is protected, and what it decides is exactly what this test is about. */
class OpenedHttpSignatureService extends HttpSignatureService
{
    /** @param array<string, mixed> $actor */
    public function owner(string $keyId, array $actor): string
    {
        return $this->keyOwner($keyId, $actor);
    }
}

/** A verifier whose clock and HTTP client the test sets. */
class ClockedHttpSignatureService extends HttpSignatureService
{
    public int $clock = 0;

    public function useClient(\Symfony\Contracts\HttpClient\HttpClientInterface $client): void
    {
        $this->httpClient = $client;
    }

    protected function now(): int
    {
        return $this->clock;
    }
}
