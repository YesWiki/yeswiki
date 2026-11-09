<?php

namespace YesWiki\Test\Federation;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Federation\Message\DeliverActivity;
use YesWiki\Federation\Message\DeliverActivityHandler;
use YesWiki\Federation\Service\ActivityPubKeyStore;
use YesWiki\Federation\Service\ActivityPubLog;
use YesWiki\Federation\Service\ActivityPubProfiles;
use YesWiki\Federation\Service\ActivityPubService;
use YesWiki\Federation\Service\FederationQueueHealthChecks;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\Severity;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\HealthService;
use YesWiki\Kernel\Service\KeyPairGenerator;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\SsrfUrlValidator;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestKit;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Federation/RecordingJournal.php';
require_once 'src/constants.php';

/**
 * Followers' deliveries go through the queue, one message per recipient (ADR-0030).
 *
 * The queue is the kit's throw-away one, with the real handler on it, so that a delivery is
 * consumed the way a pass would; the fediverse is Symfony's MockHttpClient.
 */
#[CoversClass(DeliverActivity::class)]
#[CoversClass(DeliverActivityHandler::class)]
#[CoversClass(FederationQueueHealthChecks::class)]
#[CoversClass(ActivityPubService::class)]
class DeliverActivityTest extends YesWikiTestCase
{
    private const US = 'https://us.example/actors/1';
    private const ALICE = 'https://alice.example/users/alice';
    private const BOB = 'https://bob.example/users/bob';
    private const CARL = 'https://carl.example/users/carl';

    private QueueTestKit $kit;
    private string $root;
    private RecordingJournal $journal;

    /** @var list<string> the followers of the form */
    private array $followers = [];
    /** @var list<string> the actors whose inbox answers 500 */
    private array $dead = [];
    /** @var list<string> the inboxes that were posted to, one entry per attempt */
    private array $posted = [];
    /** @var list<string> the bodies of those posts */
    private array $postedBodies = [];
    /** @var list<array<string, string>> the headers of those posts, by lower-cased name */
    private array $postedHeaders = [];
    /** @var array<string, mixed>|null the form the handler finds again, null once it is gone */
    private ?array $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getWiki(); // before the kit points the environment at its own configuration
        $this->root = sys_get_temp_dir() . '/yeswiki-deliver-' . bin2hex(random_bytes(4));
        $this->journal = new RecordingJournal();
        $this->form = ['id' => '1', 'label' => 'Agenda', 'activitypub_enable' => '1', 'activitypub' => ['username' => 'agenda']];
        $this->kit = new QueueTestKit([
            DeliverActivity::class => fn (DeliverActivity $message) => $this->handler()($message),
        ]);
    }

    protected function tearDown(): void
    {
        $this->kit->dispose();
        Storage::rootedAt($this->root)->deleteDirectory(ActivityPubKeyStore::DIRECTORY);
        foreach (['/private/keys', '/private', ''] as $directory) {
            @rmdir($this->root . $directory);
        }
    }

    public function testAFollowedFormSavesOneMessagePerFollower(): void
    {
        $this->followers = [self::ALICE, self::BOB, self::CARL];

        $this->service()->notifyFollowers($this->form(), ['tag' => 'Fiche'], 'Create');

        $this->assertSame([], $this->posted, 'nothing is sent while the person saves');
        $this->assertSame(3, $this->kit->statistics->pendingCount());
        $messages = array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $this->kit->waiting());
        $this->assertContainsOnlyInstancesOf(DeliverActivity::class, $messages);
        $this->assertEqualsCanonicalizing([self::ALICE, self::BOB, self::CARL], array_column($messages, 'recipientUri'));
        $this->assertSame(['1'], array_values(array_unique(array_column($messages, 'formId'))));
        $this->assertCount(1, array_unique(array_column($messages, 'activityJson')), 'the activity is prepared once and carried as it is');
        $activity = json_decode($messages[0]->activityJson, true);
        $this->assertSame('Create', $activity['type']);
        $this->assertSame(self::US, $activity['actor']);
        $this->assertSame(self::US, $messages[0]->actorUri(), 'the delivery signs as the actor of its activity, not carried a second time');
        $this->assertSame('https://www.w3.org/ns/activitystreams', $activity['@context']);
        $this->assertStringStartsWith(self::US . '/activities/', $activity['id']);
    }

    public function testTheDeliveryHealthChecksAreRegistered(): void
    {
        $ids = $this->getWiki()->services->get(HealthService::class)->ids();

        $this->assertContains('federation-delivery', $ids);
    }

    public function testNoFollowerNoMessage(): void
    {
        $this->service()->notifyFollowers($this->form(), ['tag' => 'Fiche'], 'Create');

        $this->assertSame(0, $this->kit->statistics->pendingCount());
    }

    public function testConsumingTheMessagesDeliversToEachInboxAndLogsIt(): void
    {
        $this->followers = [self::ALICE, self::BOB];
        $this->service()->notifyFollowers($this->form(), ['tag' => 'Fiche'], 'Create');

        $report = $this->drain();

        $this->assertSame(2, $report->handled);
        $this->assertEqualsCanonicalizing([self::ALICE . '/inbox', self::BOB . '/inbox'], $this->posted);
        $actions = array_column(array_column($this->journal->records, 'context'), 'action');
        $this->assertSame(['activitypub.delivered', 'activitypub.delivered'], $actions);
    }

    public function testADeliveryFailureIsRetriedFiveTimes(): void
    {
        $this->followers = [self::ALICE];
        $this->dead = [self::ALICE];
        $this->service()->notifyFollowers($this->form(), ['tag' => 'Fiche'], 'Create');

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->assertCount(1, $this->waiting(), "attempt {$attempt} has a message to take");
            $this->kit->consumer->handleById($this->idOf($this->waiting()[0]));
        }

        $this->assertCount(6, $this->posted, 'the first try and five more');
        $this->assertSame(0, $this->kit->statistics->pendingCount(), 'no seventh try');
        $this->assertSame(0, $this->kit->statistics->failedCount(), 'a delivery that gave up is in the history, not in failed');
        $this->assertSame(
            array_fill(0, 5, 'retried') + [5 => 'failed'],
            array_column(array_reverse($this->kit->statistics->history(10)), 'status')
        );
        $this->assertCount(6, array_filter(
            array_column($this->journal->records, 'context'),
            static fn (array $context): bool => $context['action'] === 'activitypub.delivery_failed'
        ), 'every attempt keeps feeding the ActivityPub log');
    }

    /** Deleting a form deletes its entries first, each telling the followers, and then the form: the deliveries leave after it is gone. */
    public function testDeletingAFormStillDeliversTheDeletesOfItsEntries(): void
    {
        $this->followers = [self::ALICE, self::BOB];
        $service = $this->service();
        $service->notifyFollowers($this->form(), ['tag' => 'FicheA'], 'Delete');
        $service->notifyFollowers($this->form(), ['tag' => 'FicheB'], 'Delete');
        $this->assertSame(4, $this->kit->statistics->pendingCount());
        $this->form = null;
        $this->followers = [];

        $report = $this->drain();

        $this->assertSame(4, $report->handled);
        $this->assertCount(4, $this->posted, 'the Tombstones are delivered although the form is gone');
        $this->assertSame(0, $this->kit->statistics->failedCount());
        $this->assertSame(0, $this->kit->statistics->outcomes(3600, DeliverActivity::class)['failed']);
        $this->assertNull($this->check('federation-delivery')->failure());
    }

    public function testTheBodyIsSentAsItWasPrepared(): void
    {
        $this->followers = [self::ALICE];
        $this->service()->notifyFollowers($this->form(), ['tag' => 'Fiche'], 'Create');
        $message = $this->waiting()[0]->getMessage();
        $this->assertInstanceOf(DeliverActivity::class, $message);
        $json = $message->activityJson;

        $this->drain();

        $this->assertSame($json, $this->postedBodies[0], 'byte for byte: an empty object stays one');
    }

    /** What every remote server checks: the Signature names the actor's `#main-key` and was made with the private key of the form. */
    public function testTheDeliveryIsSignedWithTheKeyOfTheFormUnderTheActorsKeyId(): void
    {
        $this->followers = [self::ALICE];
        $this->service()->notifyFollowers($this->form(), ['tag' => 'Fiche'], 'Create');

        $this->drain();

        $this->assertCount(1, $this->postedHeaders);
        $headers = $this->postedHeaders[0];
        $this->assertSame(4, preg_match_all('/(\w+)="([^"]*)"/', $headers['signature'], $matches));
        $signature = array_combine($matches[1], $matches[2]);
        $this->assertSame(self::US . '#main-key', $signature['keyId']);
        $this->assertSame('(request-target) host date digest content-type', $signature['headers']);
        $signed = implode("\n", [
            '(request-target): post /users/alice/inbox',
            'host: alice.example',
            'date: ' . $headers['date'],
            'digest: ' . $headers['digest'],
            'content-type: ' . $headers['content-type'],
        ]);
        $keys = new ActivityPubKeyStore(Storage::rootedAt($this->root), new KeyPairGenerator());
        $this->assertSame(1, openssl_verify($signed, (string)base64_decode($signature['signature']), $keys->publicKey('1'), OPENSSL_ALGO_SHA256), 'verifiable with the public key of the form');
    }

    public function testTheHandlerGivesUpWhenTheKeyIsGone(): void
    {
        $this->followers = [self::ALICE];
        $this->service()->notifyFollowers($this->form(), ['tag' => 'Fiche'], 'Create');
        Storage::rootedAt($this->root)->deleteDirectory(ActivityPubKeyStore::DIRECTORY);

        $this->kit->consumer->handleById($this->idOf($this->waiting()[0]));

        $this->assertSame([], $this->posted, 'a new key would be one nobody knows');
        $this->assertSame(0, $this->kit->statistics->pendingCount(), 'no retry can bring the key back');
        $this->assertSame(0, $this->kit->statistics->failedCount(), 'its failure stays in the history');
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('key of the form 1 is gone');
        $this->handler()(new DeliverActivity('1', self::ALICE, (string)json_encode(['actor' => self::US])));
    }

    /** The key is named after the activity's actor: an activity with none can never be signed, however often it is tried. */
    public function testTheHandlerGivesUpOnAnActivityWithoutActor(): void
    {
        $this->assertSame('', (new DeliverActivity('1', self::ALICE, '{}'))->actorUri());

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('names no actor');
        $this->handler()(new DeliverActivity('1', self::ALICE, '{}'));
    }

    public function testAFormWithoutUsernameQueuesNothing(): void
    {
        $this->followers = [self::ALICE];
        $this->form = ['id' => '1', 'activitypub_enable' => '1'];

        $this->service()->notifyFollowers($this->form(), ['tag' => 'Fiche'], 'Create');

        $this->assertSame(0, $this->kit->statistics->pendingCount());
    }

    public function testFollowAndAcceptStaySynchronous(): void
    {
        $service = $this->service();

        $service->follow($this->form(), self::ALICE);
        $service->receiveFollow($this->form(), ['type' => 'Follow', 'id' => self::BOB . '#follows/1', 'actor' => self::BOB, 'object' => self::US]);

        $this->assertSame([self::ALICE . '/inbox', self::BOB . '/inbox'], $this->posted, 'sent at once');
        $this->assertSame(0, $this->kit->statistics->pendingCount(), 'the queue got nothing');
        $this->assertSame(0, $this->kit->statistics->failedCount());
    }

    public function testAFailingFollowStillThrowsToItsCaller(): void
    {
        $this->dead = [self::ALICE];

        $this->expectExceptionMessage('Failed to deliver Follow');
        $this->service()->follow($this->form(), self::ALICE);
    }

    public function testAllDeliveriesFailingIsBroken(): void
    {
        $this->history(QueueRecorder::FAILED, 5);

        $check = $this->check('federation-delivery');

        $this->assertNotNull($check->failure());
        $this->assertSame(Severity::Broken, $check->severity());
    }

    public function testFewDeliveriesOrOneSuccessAreNotAnOutage(): void
    {
        $this->history(QueueRecorder::FAILED, 4);
        $this->assertNull($this->check('federation-delivery')->failure(), 'four failures say nothing yet');

        $this->history(QueueRecorder::KILLED, 1);
        $this->assertNotNull($this->check('federation-delivery')->failure(), 'a killed delivery is a finished one');

        $this->history(QueueRecorder::HANDLED, 1);
        $this->assertNull($this->check('federation-delivery')->failure(), 'one that went through is enough');
    }

    public function testWithoutDeliveriesThereIsNothingToSay(): void
    {
        $this->assertNull($this->check('federation-delivery')->failure());
    }

    public function testAKilledDeliveryStaysInFailedAndRaisesTheKilledAlert(): void
    {
        $stamp = $this->kit->transport->send(new Envelope(new DeliverActivity('1', self::ALICE, '{}')))->last(TransportMessageIdStamp::class);
        $this->assertNotNull($stamp);
        QueueTestKit::failAsKilled($this->kit->db, $this->kit->schema, $this->kit->clock, (int)$stamp->getId(), QueueConsumerMode::Request);

        $this->assertSame('killed', $this->kit->statistics->messageState((int)$stamp->getId()));
        $this->assertSame('request', $this->kit->statistics->failed()[0]['killedIn']);
        $this->assertNotNull($this->queueCheck('queue-killed')->failure());
        $this->assertNull($this->queueCheck('queue-failed')->failure(), 'a killed message is the killed alert\'s');
        $this->assertNull($this->check('federation-delivery')->failure());
    }

    public function testTheChecksStaySilentWhenTheQueueCannotBeRead(): void
    {
        $this->kit->dropTables();

        $this->assertNull($this->check('federation-delivery')->failure(), 'queue-tables is the one to speak');
    }

    // --- Helpers -----------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function form(): array
    {
        return $this->form ?? [];
    }

    private function check(string $id): HealthCheck
    {
        foreach ((new FederationQueueHealthChecks($this->kit->statistics))->healthChecks() as $check) {
            if ($check->id() === $id) {
                return $check;
            }
        }
        $this->fail("No {$id} check");
    }

    private function queueCheck(string $id): HealthCheck
    {
        $settings = new QueueSettings(new ParameterBag(['queue_consumer' => '', 'queue_consume_secret' => '']));
        foreach ($this->kit->healthChecks($settings)->healthChecks() as $check) {
            if ($check->id() === $id) {
                return $check;
            }
        }
        $this->fail("No {$id} check");
    }

    /** Deliveries that ended as that, in the history. */
    private function history(string $status, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->kit->recorder->messageFinished($i + 1, DeliverActivity::class, null, $status, QueueConsumerMode::Api, microtime(true), $status === QueueRecorder::HANDLED ? null : 'HTTP 410');
        }
    }

    private function drain(): \YesWiki\Kernel\Queue\QueuePassReport
    {
        return $this->kit->consumer->drain(QueueConsumerMode::Api, (new QueuePassLimitsFactory(new NativeClock()))->forPass());
    }

    /** @return list<Envelope> */
    private function waiting(): array
    {
        return $this->kit->waiting();
    }

    private function idOf(Envelope $envelope): int
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        $this->assertNotNull($stamp);

        return (int)$stamp->getId();
    }

    private function handler(): DeliverActivityHandler
    {
        return new DeliverActivityHandler($this->service());
    }

    /** The service the wiki would have, over the kit's queue and a fediverse that answers as the test says. */
    private function service(): ActivityPubService
    {
        $tripleStore = $this->createStub(TripleStore::class);
        $triples = [];
        foreach ($this->followers as $follower) {
            $triples[] = ['resource' => self::US . '/followers', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => $follower];
        }
        $tripleStore->method('getMatching')->willReturnCallback(
            function ($resource, $property) use (&$triples) {
                return array_values(array_filter(
                    $triples,
                    static fn (array $triple): bool => $triple['resource'] === $resource && $triple['property'] === $property
                ));
            }
        );
        $tripleStore->method('create')->willReturnCallback(function ($resource, $property, $value) use (&$triples) {
            $triples[] = ['resource' => $resource, 'property' => $property, 'value' => $value];

            return 0;
        });
        $tripleStore->method('delete')->willReturnCallback(function ($resource, $property, $value) use (&$triples) {
            $triples = array_values(array_filter(
                $triples,
                static fn (array $triple): bool => !($triple['resource'] === $resource && $triple['property'] === $property && ($value === null || $triple['value'] === $value))
            ));

            return true;
        });
        $params = $this->createStub(ParameterBagInterface::class);
        $params->method('get')->willReturn('https://us.example/?');
        $semanticTransformer = $this->createStub(SemanticTransformer::class);
        $semanticTransformer->method('convertToSemanticData')->willReturn(['@context' => 'x', 'id' => 'https://us.example/?Fiche', 'type' => 'Note']);

        $client = new MockHttpClient(function (string $method, string $url, array $options) {
            if ($method === 'GET') {
                return new MockResponse((string)json_encode(['id' => $url, 'inbox' => $url . '/inbox']));
            }
            $this->posted[] = $url;
            $this->postedBodies[] = $options['body'];
            $headers = [];
            foreach ($options['headers'] as $line) {
                [$name, $value] = explode(': ', $line, 2);
                $headers[strtolower($name)] = $value;
            }
            $this->postedHeaders[] = $headers;
            $actor = substr($url, 0, -strlen('/inbox'));

            return new MockResponse('', ['http_code' => in_array($actor, $this->dead, true) ? 500 : 202]);
        });
        $httpSignatureService = new \YesWiki\Federation\Service\HttpSignatureService($this->createStub(SsrfUrlValidator::class));
        (new \ReflectionProperty($httpSignatureService, 'httpClient'))->setValue($httpSignatureService, $client);

        $service = new ActivityPubService(
            $params,
            $httpSignatureService,
            $semanticTransformer,
            $tripleStore,
            $this->createStub(SsrfUrlValidator::class),
            new ActivityPubKeyStore(Storage::rootedAt($this->root), new KeyPairGenerator()),
            new ActivityPubProfiles($this->createStub(ContainerInterface::class)),
            new ActivityPubLog(new ParameterBag(['activitypub_log' => true]), $this->journal),
            $this->kit->bus,
        );
        (new \ReflectionProperty($service, 'httpClient'))->setValue($service, $client);

        return $service;
    }
}
