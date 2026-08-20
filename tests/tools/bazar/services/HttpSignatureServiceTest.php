<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Bazar\Service\HttpSignatureService;
use YesWiki\Bazar\Service\SsrfUrlValidator;

require_once 'includes/autoload.inc.php';
require_once 'includes/constants.php';

class HttpSignatureServiceTest extends TestCase
{
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
