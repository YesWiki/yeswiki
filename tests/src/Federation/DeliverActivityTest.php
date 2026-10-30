<?php

namespace YesWiki\Test\Federation;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
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
use YesWiki\Kernel\Queue\QueueRetryStrategy;
use YesWiki\Kernel\Queue\RetryPolicy;
use YesWiki\Kernel\Queue\ToleratedFailure;
use YesWiki\Kernel\Service\HealthService;
use YesWiki\Kernel\Service\KeyPairGenerator;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueHealthChecks;
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
 * Followers' deliveries go through the queue, one message per recipient (messenger / scheduler, spec 9.2).
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
    /** @var array<string, mixed>|null the form the handler finds again, null once it is gone */
    private ?array $form;

    protected function setUp(): void
    {
        parent::setUp();
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
        $messages = array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), [...$this->kit->transport->all()]);
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

    /** What makes `dispatch()` enough: the message is found by its type on the container the wiki really runs on (delete cache/container/* after adding a service class). */
    public function testTheHandlerAndTheChecksAreDiscoveredOnTheRealContainer(): void
    {
        $services = $this->getWiki()->services;

        $this->assertContains(DeliverActivity::class, $services->get(QueueHandlerLocator::class)->handledTypes());
        $this->assertInstanceOf(DeliverActivityHandler::class, $services->get(DeliverActivityHandler::class));
        $this->assertInstanceOf(ActivityPubService::class, $services->get(ActivityPubService::class));
        $ids = $services->get(HealthService::class)->ids();
        $this->assertContains('federation-delivery', $ids);
        $this->assertContains('federation-delivery-failed', $ids);
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

        $message = $this->waiting()[0]->getMessage();
        $this->assertInstanceOf(RetryPolicy::class, $message);
        $this->assertSame(5, $message->maxRetries(), 'more patient than the default 3');

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->assertCount(1, $this->waiting(), "attempt {$attempt} has a message to take");
            $this->kit->consumer->handleById($this->idOf($this->waiting()[0]), QueueConsumerMode::Command);
        }

        $this->assertCount(6, $this->posted, 'the first try and five more');
        $this->assertSame(0, $this->kit->statistics->pendingCount(), 'no seventh try');
        $this->assertSame(1, $this->kit->statistics->failedCount());
        $this->assertSame([DeliverActivity::class], array_column($this->kit->statistics->failed(), 'type'));
        $this->assertSame(
            array_fill(0, 5, 'retried') + [5 => 'failed'],
            array_column(array_reverse($this->kit->statistics->history(10)), 'status')
        );
        $this->assertCount(6, array_filter(
            array_column($this->journal->records, 'context'),
            static fn (array $context): bool => $context['action'] === 'activitypub.delivery_failed'
        ), 'every attempt keeps feeding the ActivityPub log');
    }

    public function testTheRetryPolicyIsTheMessagesOwn(): void
    {
        $strategy = new QueueRetryStrategy();
        $envelope = new Envelope(new DeliverActivity('1', self::ALICE, '{}'));

        for ($retries = 0; $retries < 5; $retries++) {
            $this->assertTrue($strategy->isRetryable($envelope->with(new RedeliveryStamp($retries))), "retry number {$retries}");
        }
        $this->assertFalse($strategy->isRetryable($envelope->with(new RedeliveryStamp(5))));
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
        $this->assertSame(0, $this->kit->statistics->outcomesOfType(DeliverActivity::class, 3600)['failed']);
        $this->assertNull($this->check('federation-delivery')->failure());
        $this->assertNull($this->check('federation-delivery-failed')->failure());
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

    public function testTheHandlerGivesUpWhenTheKeyIsGone(): void
    {
        $this->followers = [self::ALICE];
        $this->service()->notifyFollowers($this->form(), ['tag' => 'Fiche'], 'Create');
        Storage::rootedAt($this->root)->deleteDirectory(ActivityPubKeyStore::DIRECTORY);

        $this->kit->consumer->handleById($this->idOf($this->waiting()[0]), QueueConsumerMode::Command);

        $this->assertSame([], $this->posted, 'a new key would be one nobody knows');
        $this->assertSame(0, $this->kit->statistics->pendingCount(), 'no retry can bring the key back');
        $this->assertSame(1, $this->kit->statistics->failedCount());
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

    public function testDeadInstancesAreDegradedNotBroken(): void
    {
        $this->history(QueueRecorder::HANDLED, 10);
        $this->history(QueueRecorder::FAILED, 3);
        $this->failed(3);

        $delivery = $this->check('federation-delivery');
        $failed = $this->check('federation-delivery-failed');

        $this->assertNull($delivery->failure(), 'most deliveries go through: the instance is fine');
        $this->assertNotNull($failed->failure());
        $this->assertStringContainsString('3', (string)$failed->failure());
        $this->assertSame(Severity::Degraded, $failed->severity(), 'no badge for servers that are gone');
        $this->assertSame('admin/jobs', $failed->route());
        $this->assertSame('admin/jobs', $delivery->route());
        $this->assertSame(
            [QueueHealthChecks::offeringFailedOf(DeliverActivity::class)],
            $failed->offered(),
            'the admin can acknowledge or retry every failed delivery from the alert'
        );
        $this->assertInstanceOf(ToleratedFailure::class, new DeliverActivity('1', self::ALICE, '{}'));
        $this->assertNull($this->genericFailedCheck()->failure(), 'the generic failed-jobs alert leaves them to the federation one');
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
        foreach (['federation-delivery', 'federation-delivery-failed'] as $id) {
            $this->assertNull($this->check($id)->failure(), $id);
        }
    }

    public function testAKilledDeliveryIsTheKilledAlertsNotTheDeliveryOnes(): void
    {
        $stamp = $this->kit->transport->send(new Envelope(new DeliverActivity('1', self::ALICE, '{}')))->last(TransportMessageIdStamp::class);
        $this->assertNotNull($stamp);
        QueueTestKit::failAsKilled($this->kit->transport, (int)$stamp->getId(), QueueConsumerMode::Request);

        $this->assertNull($this->check('federation-delivery-failed')->failure());
    }

    public function testTheChecksStaySilentWhenTheQueueCannotBeRead(): void
    {
        $this->kit->dropTables();

        foreach (['federation-delivery', 'federation-delivery-failed'] as $id) {
            $this->assertNull($this->check($id)->failure(), "{$id}: queue-tables is the one to speak");
        }
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

    private function genericFailedCheck(): HealthCheck
    {
        $settings = new QueueSettings(new ParameterBag(['queue_consumer' => '', 'queue_consume_secret' => '']), static fn (): array => []);
        foreach ($this->kit->healthChecks($settings)->healthChecks() as $check) {
            if ($check->id() === 'queue-failed') {
                return $check;
            }
        }
        $this->fail('No queue-failed check');
    }

    /** Deliveries that ended as that, in the history. */
    private function history(string $status, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->kit->recorder->messageFinished($i + 1, DeliverActivity::class, null, $status, QueueConsumerMode::Command, microtime(true), $status === QueueRecorder::HANDLED ? null : 'HTTP 410');
        }
    }

    /** Deliveries waiting in `failed`. */
    private function failed(int $count, string $error = 'Exception: HTTP 410'): void
    {
        for ($i = 0; $i < $count; $i++) {
            $stamp = $this->kit->transport->send(new Envelope(new DeliverActivity('1', self::ALICE, '{}')))->last(TransportMessageIdStamp::class);
            $this->assertNotNull($stamp);
            $this->kit->transport->moveToFailed((int)$stamp->getId(), $error);
        }
    }

    private function drain(): \YesWiki\Kernel\Queue\QueuePassReport
    {
        return $this->kit->consumer->drain(QueueConsumerMode::Command, (new QueueBudgetFactory())->forMode(QueueConsumerMode::Command, true));
    }

    /** @return list<Envelope> */
    private function waiting(): array
    {
        return [...$this->kit->transport->all()];
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
