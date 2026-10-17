<?php

namespace YesWiki\Test\Federation;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Federation\Service\ActivityPubKeyStore;
use YesWiki\Federation\Service\ActivityPubProfiles;
use YesWiki\Federation\Service\ActivityPubService;
use YesWiki\Federation\Service\HttpSignatureService;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\KeyPairGenerator;
use YesWiki\Kernel\Service\SsrfUrlValidator;
use YesWiki\Kernel\Service\TripleStore;

require_once 'tests/YesWikiTestCase.php';
require_once 'src/constants.php';

/** A form's actor: its profile and key, the follows it sends and receives, and what it tells its followers. */
class ActivityPubServiceTest extends TestCase
{
    private const US = 'https://us.example/actors/1';
    private const THEM = 'https://them.example/users/them';

    private string $root;
    /** @var list<array{resource: string, property: string, value: string}> */
    private array $triples = [];
    /** @var list<array{url: string, body: array<string, mixed>, headers: array<string, list<string>>}> */
    private array $posted = [];
    /** @var list<string> */
    private array $unreachable = [];
    /** @var array<string, mixed> */
    private array $profile = ['username' => 'agenda'];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yeswiki-activitypub-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Storage::rootedAt($this->root)->deleteDirectory(ActivityPubKeyStore::DIRECTORY);
        foreach (['/private/keys', '/private', ''] as $directory) {
            @rmdir($this->root . $directory);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'id' => '1',
            'label' => 'Agenda',
            'description' => "Les événements du collectif.\nVoir https://us.example/",
            'activitypub_enable' => '1',
            'activitypub' => $this->profile,
        ];
    }

    private function service(): ActivityPubService
    {
        $tripleStore = $this->createStub(TripleStore::class);
        $tripleStore->method('getMatching')->willReturnCallback(
            fn ($resource, $property) => array_values(array_filter(
                $this->triples,
                fn ($t) => $t['resource'] === $resource && $t['property'] === $property
            ))
        );
        $tripleStore->method('create')->willReturnCallback(function ($resource, $property, $value) {
            $this->triples[] = ['resource' => $resource, 'property' => $property, 'value' => $value];

            return 0;
        });
        $tripleStore->method('delete')->willReturnCallback(function ($resource, $property, $value) {
            $this->triples = array_values(array_filter(
                $this->triples,
                fn ($t) => !($t['resource'] === $resource && $t['property'] === $property && ($value === null || $t['value'] === $value))
            ));

            return true;
        });

        $params = $this->createStub(ParameterBagInterface::class);
        $params->method('get')->willReturn('https://us.example/?');

        $semanticTransformer = $this->createStub(SemanticTransformer::class);
        $semanticTransformer->method('convertToSemanticData')->willReturn(['@context' => 'x', 'id' => 'https://us.example/?Fiche', 'type' => 'Note']);

        $client = new MockHttpClient(function ($method, $url, $options) {
            if ($method === 'GET') {
                if (in_array($url, $this->unreachable, true)) {
                    return new MockResponse('', ['http_code' => 500]);
                }

                return new MockResponse((string)json_encode(['id' => $url, 'inbox' => $url . '/inbox']));
            }
            $this->posted[] = ['url' => $url, 'body' => json_decode($options['body'], true), 'headers' => $options['normalized_headers']];

            return new MockResponse('', ['http_code' => 202]);
        });

        $httpSignatureService = new HttpSignatureService($this->createStub(SsrfUrlValidator::class));
        (new \ReflectionProperty($httpSignatureService, 'httpClient'))->setValue($httpSignatureService, $client);

        $service = new ActivityPubService(
            $params,
            $httpSignatureService,
            $semanticTransformer,
            $tripleStore,
            $this->createStub(SsrfUrlValidator::class),
            new ActivityPubKeyStore(Storage::rootedAt($this->root), new KeyPairGenerator()),
            new ActivityPubProfiles($this->createStub(ContainerInterface::class)),
        );
        (new \ReflectionProperty($service, 'httpClient'))->setValue($service, $client);

        return $service;
    }

    public function testTheActorDeclaresTheSecurityContextItsKeyNeeds(): void
    {
        $actor = $this->service()->getActor($this->form());

        $this->assertContains('https://w3id.org/security/v1', $actor['@context']);
        $this->assertSame(self::US . '#main-key', $actor['publicKey']['id']);
    }

    public function testTheKeyIsGeneratedOnceInProtectedStorageAndMatchesTheActor(): void
    {
        $service = $this->service();

        $actor = $service->getActor($this->form());
        $signer = $service->signer($this->form());

        $this->assertNotNull($signer);
        $this->assertFileExists($this->root . '/' . ActivityPubKeyStore::DIRECTORY . '/1.pem');
        $key = openssl_pkey_get_private($signer['privateKey']);
        $this->assertNotFalse($key);
        $this->assertSame((openssl_pkey_get_details($key) ?: [])['key'] ?? null, $actor['publicKey']['publicKeyPem']);
        $this->assertSame($actor['publicKey']['publicKeyPem'], $this->service()->getActor($this->form())['publicKey']['publicKeyPem']);
    }

    public function testAFormIdCannotReachOutsideTheKeyDirectory(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid ActivityPub account');

        $this->service()->signer($this->form(['id' => '../signing']));
    }

    public function testAFormWithoutUsernameHasNoActor(): void
    {
        $this->profile = [];

        $this->assertFalse($this->service()->hasActor($this->form()));
        $this->assertNull($this->service()->signer($this->form()));
    }

    public function testADisabledFormHasNoActorEvenWithAUsername(): void
    {
        $this->assertFalse($this->service()->hasActor($this->form(['activitypub_enable' => '0'])));
    }

    public function testChangingTheUsernameKeepsTheKey(): void
    {
        $before = $this->service()->getActor($this->form());
        $this->profile['username'] = 'evenements';
        $after = $this->service()->getActor($this->form());

        $this->assertSame('evenements', $after['preferredUsername']);
        $this->assertSame($before['publicKey']['publicKeyPem'], $after['publicKey']['publicKeyPem']);
    }

    public function testTheActorShowsItsProfile(): void
    {
        $this->profile = [
            'username' => 'agenda',
            'icon' => 'files/Logo.png',
            'image' => 'https://cdn.example/banner.jpg',
            'fields' => ['Site' => 'https://us.example/', 'Empty' => '', 'Contact' => 'agenda@us.example'],
        ];

        $actor = $this->service()->getActor($this->form());

        $this->assertSame(['type' => 'Image', 'url' => 'https://us.example/files/Logo.png', 'mediaType' => 'image/png'], $actor['icon']);
        $this->assertSame('https://cdn.example/banner.jpg', $actor['image']['url']);
        $this->assertSame(['Site', 'Contact'], array_column($actor['attachment'], 'name'));
        $this->assertStringContainsString('<a href="https://us.example/"', $actor['attachment'][0]['value']);
        $this->assertSame('https://us.example/', $actor['url']);
    }

    public function testTheProfileSummaryReplacesTheDescription(): void
    {
        $this->profile = ['username' => 'agenda', 'summary' => 'Agenda des rencontres'];

        $this->assertSame('<p>Agenda des rencontres</p>', $this->service()->getActor($this->form())['summary']);
    }

    public function testTheSummaryFallsBackToTheFormDescription(): void
    {
        $actor = $this->service()->getActor($this->form());

        $this->assertStringStartsWith('<p>Les événements du collectif.<br>', $actor['summary']);
        $this->assertArrayNotHasKey('icon', $actor);
        $this->assertArrayNotHasKey('attachment', $actor);
    }

    public function testEachFollowHasItsOwnIdAndOnlyItsAcceptCounts(): void
    {
        $service = $this->service();

        $service->follow($this->form(), self::THEM);
        $first = $this->posted[0]['body']['id'];
        $service->follow($this->form(), self::THEM);
        $second = $this->posted[1]['body']['id'];

        $this->assertNotSame($first, $second);
        $this->assertSame([self::THEM], $service->getPendingFollowing($this->form()));
        $this->assertFalse($service->acceptFollow($this->form(), self::THEM, $first));
        $this->assertTrue($service->acceptFollow($this->form(), self::THEM, $second));
        $this->assertSame([self::THEM], $service->getFollowing($this->form()));
        $this->assertSame([], $service->getPendingFollowing($this->form()));
    }

    public function testUnfollowUndoesTheFollowThatWasSent(): void
    {
        $service = $this->service();
        $service->follow($this->form(), self::THEM);
        $followId = $this->posted[0]['body']['id'];

        $service->unfollow($this->form(), self::THEM);

        $this->assertSame('Undo', $this->posted[1]['body']['type']);
        $this->assertSame($followId, $this->posted[1]['body']['object']['id']);
    }

    public function testDeliveriesAreSignedOverTheirBody(): void
    {
        $this->service()->follow($this->form(), self::THEM);

        $headers = $this->posted[0]['headers'];
        $this->assertStringContainsString('headers="(request-target) host date digest content-type"', $headers['signature'][0]);
        $this->assertSame('Content-Type: application/activity+json', $headers['content-type'][0]);
    }

    public function testFollowersAreNotifiedPubliclyAndOneUnreachableFollowerDoesNotStopTheOthers(): void
    {
        $this->triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => 'https://down.example/users/a'];
        $this->triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => self::THEM];
        $this->unreachable[] = 'https://down.example/users/a';

        $this->service()->notifyFollowers($this->form(), ['tag' => 'Fiche'], 'Create');

        $this->assertCount(1, $this->posted);
        $activity = $this->posted[0]['body'];
        $this->assertSame([ActivityPubService::PUBLIC], $activity['to']);
        $this->assertSame(self::US, $activity['object']['attributedTo']);
        $this->assertArrayNotHasKey('@context', $activity['object']);
    }

    public function testAFollowIsAcceptedAtOnceWithItsOwnId(): void
    {
        $service = $this->service();

        $service->receiveFollow($this->form(), ['type' => 'Follow', 'id' => self::THEM . '#follows/1', 'actor' => self::THEM, 'object' => self::US]);

        $this->assertSame('Accept', $this->posted[0]['body']['type']);
        $this->assertSame(self::THEM . '#follows/1', $this->posted[0]['body']['object']['id']);
        $this->assertSame(self::US, $this->posted[0]['body']['object']['object']);
        $this->assertSame([self::THEM], $service->getFollowers($this->form()));
        $this->assertSame([], $service->getFollowRequests($this->form()));
    }

    public function testAFollowWhoseAcceptCannotBeDeliveredStaysARequest(): void
    {
        $this->unreachable[] = self::THEM;
        $service = $this->service();

        $service->receiveFollow($this->form(), ['type' => 'Follow', 'id' => self::THEM . '#follows/1', 'actor' => self::THEM, 'object' => self::US]);

        $this->assertSame([], $service->getFollowers($this->form()));
        $this->assertSame([self::THEM], $service->getFollowRequests($this->form()));
    }

    public function testAFollowWaitsWhenTheProfileApprovesFollowersByHand(): void
    {
        $this->profile['approve_followers'] = true;
        $service = $this->service();

        $service->receiveFollow($this->form(), ['type' => 'Follow', 'id' => self::THEM . '#follows/1', 'actor' => self::THEM, 'object' => self::US]);

        $this->assertCount(0, $this->posted);
        $this->assertTrue($service->getActor($this->form())['manuallyApprovesFollowers']);
        $this->assertSame([self::THEM], $service->getFollowRequests($this->form()));

        $service->acceptFollower($this->form(), self::THEM);

        $this->assertSame(self::THEM . '#follows/1', $this->posted[0]['body']['object']['id']);
        $this->assertSame([self::THEM], $service->getFollowers($this->form()));
        $this->assertSame([], $service->getFollowRequests($this->form()));
    }

    public function testARefusedRequestIsRejectedWithItsOwnId(): void
    {
        $this->profile['approve_followers'] = true;
        $service = $this->service();
        $service->receiveFollow($this->form(), ['type' => 'Follow', 'id' => self::THEM . '#follows/1', 'actor' => self::THEM, 'object' => self::US]);

        $service->rejectFollower($this->form(), self::THEM);

        $this->assertSame('Reject', $this->posted[0]['body']['type']);
        $this->assertSame(self::THEM . '#follows/1', $this->posted[0]['body']['object']['id']);
        $this->assertSame([], $service->getFollowRequests($this->form()));
    }

    public function testAnUndoneFollowLeavesNoRequestBehind(): void
    {
        $this->profile['approve_followers'] = true;
        $service = $this->service();
        $service->receiveFollow($this->form(), ['type' => 'Follow', 'id' => self::THEM . '#follows/1', 'actor' => self::THEM, 'object' => self::US]);

        $service->undoFollow($this->form(), self::THEM);

        $this->assertSame([], $service->getFollowRequests($this->form()));
        $this->assertSame([], $service->getFollowers($this->form()));
    }
}
