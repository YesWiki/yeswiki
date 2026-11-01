<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use YesWiki\Bazar\Service\ActivityPubKeyStore;
use YesWiki\Bazar\Service\ActivityPubLog;
use YesWiki\Bazar\Service\ActivityPubProfiles;
use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Service\HttpSignatureService;
use YesWiki\Bazar\Service\SemanticTransformer;
use YesWiki\Bazar\Service\SsrfUrlValidator;
use YesWiki\Bazar\Service\WebfingerService;
use YesWiki\Core\Service\AfterResponse;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\Service\TripleStore;

require_once 'includes/autoload.inc.php';
require_once 'includes/constants.php';
require_once 'includes/urlutils.inc.php';

class ActivityPubServiceTest extends TestCase
{
    private const US = 'https://us.example/actors/1';
    private const THEM = 'https://them.example/users/them';

    private string $keyDir;

    private array $triples = [];
    /** @var array<int,array{url:string,body:array}> */
    private array $posted = [];
    private array $unreachable = [];
    private array $profiles = ['1' => ['username' => 'agenda']];
    private string $baseUrl = 'https://us.example/?';
    private ?AfterResponse $afterResponse = null;

    protected function setUp(): void
    {
        $this->keyDir = sys_get_temp_dir() . '/yeswiki-activitypub-keys-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->keyDir . '/log/*') ?: []);
        @rmdir($this->keyDir . '/log');
        array_map('unlink', glob($this->keyDir . '/*') ?: []);
        @rmdir($this->keyDir);
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'bn_id_nature' => '1',
            'bn_label_nature' => 'Agenda',
            'bn_description' => "Les événements du collectif.\nVoir https://us.example/",
            'bn_activitypub_enable' => '1',
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
        $params->method('has')->willReturn(true);
        $params->method('get')->willReturnCallback(fn ($name) => $name === 'activitypub_profiles' ? $this->profiles : $this->baseUrl);

        $semanticTransformer = $this->createStub(SemanticTransformer::class);
        $semanticTransformer->method('convertToSemanticData')->willReturn(['@context' => 'x', 'id' => 'https://us.example/?Fiche', 'type' => 'Note']);

        $client = new MockHttpClient(function ($method, $url, $options) {
            if ($method === 'GET') {
                if (in_array($url, $this->unreachable, true)) {
                    return new MockResponse('', ['http_code' => 500]);
                }

                return new MockResponse(json_encode(['id' => $url, 'inbox' => $url . '/inbox']));
            }
            $this->posted[] = ['url' => $url, 'body' => json_decode($options['body'], true), 'headers' => $options['normalized_headers'], 'timeout' => $options['timeout'], 'max_duration' => $options['max_duration']];

            return new MockResponse('', ['http_code' => 202]);
        });

        $httpSignatureService = new HttpSignatureService($this->createStub(SsrfUrlValidator::class));
        (new \ReflectionProperty($httpSignatureService, 'httpClient'))->setValue($httpSignatureService, $client);

        $keyStore = new ActivityPubKeyStore($httpSignatureService, $this->keyDir);
        $profiles = new ActivityPubProfiles($params, new ConfigurationService(), $this->keyDir . '/wakka.config.php');
        $service = new ActivityPubService($params, $this->createStub(WebfingerService::class), $httpSignatureService, $semanticTransformer, $tripleStore, $this->createStub(SsrfUrlValidator::class), $keyStore, $profiles, new ActivityPubLog(new ParameterBag(['activitypub_log' => true]), $this->keyDir . '/log'), $this->afterResponse);
        (new \ReflectionProperty($service, 'httpClient'))->setValue($service, $client);

        return $service;
    }

    public function testTheActorDeclaresTheSecurityContextItsKeyNeeds()
    {
        $actor = $this->service()->getActor($this->form());

        $this->assertContains('https://w3id.org/security/v1', $actor['@context']);
        $this->assertSame(self::US . '#main-key', $actor['publicKey']['id']);
    }

    public function testTheKeyIsGeneratedOnceInAPrivateFileAndMatchesTheActor()
    {
        $service = $this->service();

        $actor = $service->getActor($this->form());
        $signer = $service->signer($this->form());

        $this->assertSame(['1.pem'], array_map('basename', glob($this->keyDir . '/*')));
        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->keyDir . '/1.pem')), -4));
        $this->assertSame(
            openssl_pkey_get_details(openssl_pkey_get_private($signer['privateKey']))['key'],
            $actor['publicKey']['publicKeyPem']
        );
        $this->assertSame($actor['publicKey']['publicKeyPem'], $this->service()->getActor($this->form())['publicKey']['publicKeyPem']);
    }

    public function testAFormIdCannotReachOutsideTheKeyDirectory()
    {
        $this->profiles['../wakka'] = ['username' => 'wakka'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid ActivityPub account');

        $this->service()->signer($this->form(['bn_id_nature' => '../wakka']));
    }

    public function testTheRoutesToCheckAreTheOnesOtherServersAsk()
    {
        $this->assertSame([
            'atDomainRoot' => true,
            'webfinger' => 'https://us.example/.well-known/webfinger?resource=acct%3Aagenda%40us.example',
            'actor' => self::US,
        ], $this->service()->routesToCheck($this->form()));
    }

    public function testAWikiInASubfolderIsNotAtTheDomainRoot()
    {
        $this->baseUrl = 'https://us.example/wiki/?';

        $this->assertFalse($this->service()->routesToCheck($this->form())['atDomainRoot']);
    }

    public function testAFormWithoutUsernameHasNoActor()
    {
        $this->profiles = [];

        $this->assertFalse($this->service()->hasActor($this->form()));
        $this->assertNull($this->service()->signer($this->form()));
    }

    public function testChangingTheUsernameKeepsTheKey()
    {
        $before = $this->service()->getActor($this->form());
        $this->profiles['1']['username'] = 'evenements';
        $after = $this->service()->getActor($this->form());

        $this->assertSame('evenements', $after['preferredUsername']);
        $this->assertSame($before['publicKey']['publicKeyPem'], $after['publicKey']['publicKeyPem']);
    }

    public function testTheActorShowsItsProfile()
    {
        $this->profiles = [
            '1' => [
                'username' => 'agenda',
                'icon' => 'files/Logo.png',
                'image' => 'https://cdn.example/banner.jpg',
                'fields' => ['Site' => 'https://us.example/', 'Empty' => '', 'Contact' => 'agenda@us.example'],
            ],
            '2' => ['username' => 'blog', 'icon' => 'https://cdn.example/blog.png'],
        ];

        $actor = $this->service()->getActor($this->form());

        $this->assertSame(['type' => 'Image', 'url' => 'https://us.example/files/Logo.png', 'mediaType' => 'image/png'], $actor['icon']);
        $this->assertSame('https://cdn.example/banner.jpg', $actor['image']['url']);
        $this->assertSame(['Site', 'Contact'], array_column($actor['attachment'], 'name'));
        $this->assertStringContainsString('<a href="https://us.example/"', $actor['attachment'][0]['value']);
        $this->assertSame('https://us.example/', $actor['url']);
    }

    public function testTheConfiguredSummaryReplacesTheDescription()
    {
        $this->profiles = ['1' => ['username' => 'agenda', 'summary' => 'Agenda des rencontres']];

        $this->assertSame('<p>Agenda des rencontres</p>', $this->service()->getActor($this->form())['summary']);
    }

    public function testTheSummaryFallsBackToTheFormDescription()
    {
        $actor = $this->service()->getActor($this->form());

        $this->assertStringStartsWith('<p>Les événements du collectif.<br>', $actor['summary']);
        $this->assertArrayNotHasKey('icon', $actor);
        $this->assertArrayNotHasKey('attachment', $actor);
    }

    public function testEachFollowHasItsOwnIdAndOnlyItsAcceptCounts()
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

    public function testUnfollowUndoesTheFollowThatWasSent()
    {
        $service = $this->service();
        $service->follow($this->form(), self::THEM);
        $followId = $this->posted[0]['body']['id'];

        $service->unfollow($this->form(), self::THEM);

        $this->assertSame('Undo', $this->posted[1]['body']['type']);
        $this->assertSame($followId, $this->posted[1]['body']['object']['id']);
    }

    public function testDeliveriesAreSignedOverTheirBody()
    {
        $this->service()->follow($this->form(), self::THEM);

        $headers = $this->posted[0]['headers'];
        $this->assertStringContainsString('headers="(request-target) host date digest content-type"', $headers['signature'][0]);
        $this->assertSame('Content-Type: application/activity+json', $headers['content-type'][0]);
    }

    public function testEveryDeliveryIsLoggedWithTheAnswerItGot()
    {
        $this->triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => 'https://down.example/users/a'];
        $this->triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => self::THEM];
        $this->unreachable[] = 'https://down.example/users/a';

        $this->service()->notifyFollowers($this->form(), ['id_fiche' => 'Fiche'], 'Create');

        $records = array_map(fn ($line) => json_decode($line, true), file(glob($this->keyDir . '/log/*.jsonl')[0], FILE_IGNORE_NEW_LINES));
        $this->assertSame(['out', 'out'], array_column($records, 'direction'));
        $byPeer = array_column($records, null, 'peer');
        $this->assertNull($byPeer[self::THEM]['error']);
        $this->assertSame(202, $byPeer[self::THEM]['status']);
        $this->assertSame('Create', $byPeer[self::THEM]['activity']['type']);
        $this->assertStringContainsString('500', $byPeer['https://down.example/users/a']['error']);
    }

    public function testADeletionIsSentAsATombstoneSoTheReceiverChecksTheObjectIsGone()
    {
        $this->triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => self::THEM];

        $this->service()->notifyFollowers($this->form(), ['id_fiche' => 'Fiche'], 'Delete');

        $this->assertSame('Delete', $this->posted[0]['body']['type']);
        $this->assertSame(['id' => 'https://us.example/?Fiche', 'type' => 'Tombstone', 'formerType' => 'Note'], $this->posted[0]['body']['object']);
    }

    public function testFollowersAreNotifiedPubliclyAndOneUnreachableFollowerDoesNotStopTheOthers()
    {
        $this->triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => 'https://down.example/users/a'];
        $this->triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => self::THEM];
        $this->unreachable[] = 'https://down.example/users/a';

        $this->service()->notifyFollowers($this->form(), ['id_fiche' => 'Fiche'], 'Create');

        $this->assertCount(1, $this->posted);
        $activity = $this->posted[0]['body'];
        $this->assertSame([ActivityPubService::PUBLIC], $activity['to']);
        $this->assertSame(self::US, $activity['object']['attributedTo']);
        $this->assertArrayNotHasKey('@context', $activity['object']);
    }

    public function testFollowersAreNotifiedOnlyOnceTheResponseHasGone()
    {
        $this->triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => self::THEM];
        $this->afterResponse = new class extends AfterResponse {
            public function defer(callable $task): void
            {
                $this->tasks[] = $task;
            }
        };
        $service = $this->service();

        $service->notifyFollowers($this->form(), ['id_fiche' => 'Fiche'], 'Create');
        $service->announceProfile($this->form());
        $this->assertSame([], $this->posted);

        $this->afterResponse->run();
        $this->assertSame(['Create', 'Update'], array_map(fn ($post) => $post['body']['type'], $this->posted));
    }

    public function testADeliveryGivesUpOnAFollowerThatDoesNotAnswer()
    {
        $this->triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => self::THEM];

        $this->service()->notifyFollowers($this->form(), ['id_fiche' => 'Fiche'], 'Create');

        $this->assertSame(HttpSignatureService::TIMEOUT, (int)$this->posted[0]['timeout']);
        $this->assertSame(HttpSignatureService::MAX_DURATION, (int)$this->posted[0]['max_duration']);
    }

    public function testFollowersAreToldWhenTheProfileChanges()
    {
        $this->triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => self::THEM];
        $this->profiles['1']['icon'] = 'https://us.example/files/avatar.png';
        $service = $this->service();

        $service->announceProfile($this->form());

        $activity = $this->posted[0]['body'];
        $actor = $service->getActor($this->form());
        $this->assertSame('Update', $activity['type']);
        $this->assertSame($actor['@context'], $activity['@context']);
        $this->assertSame(self::US, $activity['object']['id']);
        $this->assertSame('https://us.example/files/avatar.png', $activity['object']['icon']['url']);
        $this->assertArrayNotHasKey('@context', $activity['object']);
        $this->assertSame([ActivityPubService::PUBLIC], $activity['to']);
    }

    public function testAFormWithoutActorAnnouncesNothing()
    {
        $this->triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => self::THEM];
        $this->profiles = [];

        $this->service()->announceProfile($this->form());

        $this->assertSame([], $this->posted);
    }

    public function testAFollowIsAcceptedAtOnceWithItsOwnId()
    {
        $service = $this->service();

        $service->receiveFollow($this->form(), ['type' => 'Follow', 'id' => self::THEM . '#follows/1', 'actor' => self::THEM, 'object' => self::US]);

        $this->assertSame('Accept', $this->posted[0]['body']['type']);
        $this->assertSame(self::THEM . '#follows/1', $this->posted[0]['body']['object']['id']);
        $this->assertSame(self::US, $this->posted[0]['body']['object']['object']);
        $this->assertSame([self::THEM], $service->getFollowers($this->form()));
        $this->assertSame([], $service->getFollowRequests($this->form()));
    }

    public function testAFollowWhoseAcceptCannotBeDeliveredStaysARequest()
    {
        $this->unreachable[] = self::THEM;
        $service = $this->service();

        $service->receiveFollow($this->form(), ['type' => 'Follow', 'id' => self::THEM . '#follows/1', 'actor' => self::THEM, 'object' => self::US]);

        $this->assertSame([], $service->getFollowers($this->form()));
        $this->assertSame([self::THEM], $service->getFollowRequests($this->form()));
    }

    public function testAFollowWaitsWhenTheProfileApprovesFollowersByHand()
    {
        $this->profiles['1']['approve_followers'] = true;
        $service = $this->service();

        $service->receiveFollow($this->form(), ['type' => 'Follow', 'id' => self::THEM . '#follows/1', 'actor' => self::THEM, 'object' => self::US]);

        $this->assertSame([], $this->posted);
        $this->assertTrue($service->getActor($this->form())['manuallyApprovesFollowers']);
        $this->assertSame([self::THEM], $service->getFollowRequests($this->form()));

        $service->acceptFollower($this->form(), self::THEM);

        $this->assertSame(self::THEM . '#follows/1', $this->posted[0]['body']['object']['id']);
        $this->assertSame([self::THEM], $service->getFollowers($this->form()));
        $this->assertSame([], $service->getFollowRequests($this->form()));
    }

    public function testARefusedRequestIsRejectedWithItsOwnId()
    {
        $this->profiles['1']['approve_followers'] = true;
        $service = $this->service();
        $service->receiveFollow($this->form(), ['type' => 'Follow', 'id' => self::THEM . '#follows/1', 'actor' => self::THEM, 'object' => self::US]);

        $service->rejectFollower($this->form(), self::THEM);

        $this->assertSame('Reject', $this->posted[0]['body']['type']);
        $this->assertSame(self::THEM . '#follows/1', $this->posted[0]['body']['object']['id']);
        $this->assertSame([], $service->getFollowRequests($this->form()));
    }

    public function testAnUndoneFollowLeavesNoRequestBehind()
    {
        $this->profiles['1']['approve_followers'] = true;
        $service = $this->service();
        $service->receiveFollow($this->form(), ['type' => 'Follow', 'id' => self::THEM . '#follows/1', 'actor' => self::THEM, 'object' => self::US]);

        $service->undoFollow($this->form(), self::THEM);

        $this->assertSame([], $service->getFollowRequests($this->form()));
        $this->assertSame([], $service->getFollowers($this->form()));
    }
}
