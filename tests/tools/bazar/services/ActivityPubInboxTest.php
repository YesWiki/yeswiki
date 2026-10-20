<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\ActivityPubInbox;
use YesWiki\Bazar\Service\ActivityPubKeyStore;
use YesWiki\Bazar\Service\ActivityPubLog;
use YesWiki\Bazar\Service\ActivityPubProfiles;
use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\EntryOrigin;
use YesWiki\Bazar\Service\HttpSignatureService;
use YesWiki\Bazar\Service\SemanticTransformer;
use YesWiki\Bazar\Service\SsrfUrlValidator;
use YesWiki\Bazar\Service\WebfingerService;
use YesWiki\Core\Service\TripleStore;

require_once 'includes/autoload.inc.php';
require_once 'includes/constants.php';

class ActivityPubInboxTest extends TestCase
{
    private const FORM = ['bn_id_nature' => '1', 'bn_activitypub_enable' => '1'];
    private const THEM = 'https://them.example/actors/1';
    private const US = 'https://us.example/actors/1';

    /** @var array<int,array<string,string>> */
    private array $triples = [];
    /** @var array<string,string> */
    private array $owners = [];
    private array $updated = [];
    private array $deleted = [];
    private array $created = [];
    private array $createdTitles = [];
    private array $createdData = [];
    private ?string $convertedTitle = 'Une fiche';
    /** @var string[] */
    private array $refusedObjects = [];
    /** @var string[] */
    private array $turnedDownTypes = [];
    private ?HttpSignatureService $httpSignatureService = null;

    private function service(): ActivityPubInbox
    {
        $tripleStore = $this->createStub(TripleStore::class);
        $tripleStore->method('getMatching')->willReturnCallback(
            fn ($resource, $property, $value) => array_values(array_filter(
                $this->triples,
                fn ($t) => $t['property'] === $property
                    && (is_null($value) ? $t['resource'] === $resource : $t['value'] === $value)
            ))
        );
        $tripleStore->method('getOne')->willReturnCallback(
            fn ($resource, $property) => $property === ActivityPubInbox::REMOTE_ACTOR_URI
                ? ($this->owners[$resource] ?? null)
                : null
        );
        $tripleStore->method('create')->willReturnCallback(function ($resource, $property, $value) {
            if ($property === ActivityPubInbox::REMOTE_ACTOR_URI) {
                $this->owners[$resource] = $value;
            } else {
                $this->triples[] = ['resource' => $resource, 'property' => $property, 'value' => $value];
            }

            return 0;
        });
        $tripleStore->method('delete')->willReturnCallback(function ($resource, $property, $value) {
            $this->triples = array_values(array_filter(
                $this->triples,
                fn ($t) => !($t['resource'] === $resource && $t['property'] === $property && ($value === null || $t['value'] === $value))
            ));

            return true;
        });

        $entryManager = $this->createStub(EntryManager::class);
        $entryManager->method('create')->willReturnCallback(function ($formId, $data, $semantic, $sourceUrl) {
            if (in_array($sourceUrl, $this->refusedObjects, true)) {
                throw new \Exception('champs requis : Image');
            }
            $tag = 'Fiche' . (count($this->created) + 1);
            $this->created[] = ['tag' => $tag, 'sourceUrl' => $sourceUrl];
            $this->createdTitles[] = $data['bf_titre'] ?? null;
            $this->createdData[] = $data;
            $this->triples[] = ['resource' => $tag, 'property' => TripleStore::SOURCE_URL_URI, 'value' => $sourceUrl];

            return ['id_fiche' => $tag] + $data;
        });
        $entryManager->method('update')->willReturnCallback(function ($tag) {
            $this->updated[] = $tag;

            return [];
        });
        $entryManager->method('delete')->willReturnCallback(function ($tag) {
            $this->deleted[] = $tag;
            $this->triples = array_values(array_filter($this->triples, fn ($t) => $t['resource'] !== $tag));
            unset($this->owners[$tag]);
        });
        $entryManager->method('search')->willReturnCallback(fn () => array_map(
            fn ($resource) => ['id_fiche' => $resource],
            array_values(array_unique(array_map(
                fn ($t) => $t['resource'],
                array_filter($this->triples, fn ($t) => $t['property'] === TripleStore::SOURCE_URL_URI)
            )))
        ));

        $semanticTransformer = $this->createStub(SemanticTransformer::class);
        $semanticTransformer->method('convertFromSemanticData')->willReturnCallback(
            fn ($formId, $object) => in_array($object['type'] ?? null, $this->turnedDownTypes, true) ? null : ['bf_titre' => $this->convertedTitle]
        );

        $params = $this->createStub(ParameterBagInterface::class);
        $params->method('get')->willReturn('https://us.example/');

        $httpSignatureService = $this->httpSignatureService = new HttpSignatureService($this->createStub(SsrfUrlValidator::class));

        return new ActivityPubInbox(
            new ActivityPubService(
                $params,
                $this->createStub(WebfingerService::class),
                $httpSignatureService,
                $semanticTransformer,
                $tripleStore,
                $this->createStub(SsrfUrlValidator::class),
                $this->createStub(ActivityPubKeyStore::class),
                $this->createStub(ActivityPubProfiles::class),
                $this->createStub(ActivityPubLog::class)
            ),
            $entryManager,
            $httpSignatureService,
            $semanticTransformer,
            $tripleStore,
            $this->createStub(ActivityPubLog::class)
        );
    }

    private function givenMirroredEntry(string $tag, string $objectId, ?string $owner = null): void
    {
        $this->triples[] = ['resource' => $tag, 'property' => TripleStore::SOURCE_URL_URI, 'value' => $objectId];
        if (!is_null($owner)) {
            $this->owners[$tag] = $owner;
        }
    }

    private function update(string $objectId): array
    {
        return ['type' => 'Update', 'actor' => self::THEM, 'object' => ['id' => $objectId]];
    }

    private function delete(string $objectId): array
    {
        return ['type' => 'Delete', 'actor' => self::THEM, 'object' => ['id' => $objectId]];
    }

    public function testAnActivityCannotClaimAnActorTheRequestWasNotSignedFor()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', self::THEM);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('claims an actor');

        $this->service()->processActivity($this->update('https://them.example/entries/1'), self::FORM, 'https://attacker.example/actors/1');
    }

    public function testAnActorCannotUpdateAnEntryThatBelongsToAnother()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', 'https://them.example/actors/2');
        $activity = $this->update('https://them.example/entries/1');

        try {
            $this->service()->processActivity($activity, self::FORM, self::THEM);
            $this->fail('the update should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('belongs to another actor', $e->getMessage());
        }
        $this->assertSame([], $this->updated);
    }

    public function testAnActorCannotDeleteAnEntryThatBelongsToAnother()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', 'https://them.example/actors/2');

        try {
            $this->service()->processActivity($this->delete('https://them.example/entries/1'), self::FORM, self::THEM);
            $this->fail('the delete should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('belongs to another actor', $e->getMessage());
        }
        $this->assertSame([], $this->deleted);
    }

    public function testTheActorAnEntryCameFromCanUpdateAndDeleteIt()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', self::THEM);
        $service = $this->service();

        $service->processActivity($this->update('https://them.example/entries/1'), self::FORM, self::THEM);
        $service->processActivity($this->delete('https://them.example/entries/1'), self::FORM, self::THEM);

        $this->assertSame(['FicheUne'], $this->updated);
        $this->assertSame(['FicheUne'], $this->deleted);
    }

    public function testAnEntryMirroredBeforeOwnersWereRecordedAnswersToItsOwnHost()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1');

        $this->service()->processActivity($this->update('https://them.example/entries/1'), self::FORM, self::THEM);

        $this->assertSame(['FicheUne'], $this->updated);
    }

    public function testAnotherHostCannotTouchAnEntryMirroredBeforeOwnersWereRecorded()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1');
        $activity = ['type' => 'Delete', 'actor' => 'https://attacker.example/actors/1', 'object' => ['id' => 'https://them.example/entries/1']];

        try {
            $this->service()->processActivity($activity, self::FORM, 'https://attacker.example/actors/1');
            $this->fail('the delete should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('another host', $e->getMessage());
        }
        $this->assertSame([], $this->deleted);
    }

    public function testCreatingAnEntryWritesDownWhoItAnswersTo()
    {
        $activity = [
            'type' => 'Create',
            'actor' => self::THEM,
            'object' => ['id' => 'https://them.example/entries/7', 'type' => 'Note'],
        ];

        $this->service()->processActivity($activity, self::FORM, self::THEM);

        $this->assertSame([['tag' => 'Fiche1', 'sourceUrl' => 'https://them.example/entries/7']], $this->created);
        $this->assertSame([self::THEM], array_values($this->owners));
    }

    public function testAnActorCannotBringAnObjectFromAnotherHost()
    {
        $activity = [
            'type' => 'Create',
            'actor' => self::THEM,
            'object' => ['id' => 'https://us.example/entries/7', 'type' => 'Note'],
        ];

        try {
            $this->service()->processActivity($activity, self::FORM, self::THEM);
            $this->fail('the creation should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('its own host', $e->getMessage());
        }
        $this->assertSame([], $this->created);
    }

    public function testACreateForAnObjectAlreadyMirroredUpdatesItInsteadOfDuplicatingIt()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/events/1', self::THEM);

        $this->service()->processActivity(['type' => 'Create', 'actor' => self::THEM, 'object' => ['id' => 'https://them.example/events/1', 'type' => 'Event']], self::FORM, self::THEM);

        $this->assertSame([], $this->created);
        $this->assertSame(['FicheUne'], $this->updated);
    }

    public function testACreateForAnotherActorsMirroredObjectIsRefused()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/events/1', 'https://them.example/actors/2');

        try {
            $this->service()->processActivity(['type' => 'Create', 'actor' => self::THEM, 'object' => ['id' => 'https://them.example/events/1', 'type' => 'Event']], self::FORM, self::THEM);
            $this->fail('the create should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('belongs to another actor', $e->getMessage());
        }
        $this->assertSame([], $this->updated);
    }

    public function testAnObjectTheReverseTemplateTurnsDownIsNotMirrored()
    {
        $this->turnedDownTypes = ['Note'];
        $this->givenMirroredEntry('FicheUne', 'https://them.example/events/1', self::THEM);
        $service = $this->service();

        $service->processActivity(['type' => 'Create', 'actor' => self::THEM, 'object' => ['id' => 'https://them.example/comments/1', 'type' => 'Note']], self::FORM, self::THEM);
        $service->processActivity(['type' => 'Update', 'actor' => self::THEM, 'object' => ['id' => 'https://them.example/events/1', 'type' => 'Note']], self::FORM, self::THEM);

        $this->assertSame([], $this->created);
        $this->assertSame([], $this->updated);
    }

    public function testASyncSkipsWhatTheReverseTemplateTurnsDownWithoutCountingItAsAFailure()
    {
        $this->turnedDownTypes = ['Note'];

        $stats = $this->sync([
            ['type' => 'Create', 'object' => ['id' => 'https://them.example/comments/1', 'type' => 'Note']],
            ['type' => 'Create', 'object' => ['id' => 'https://them.example/events/2', 'type' => 'Event']],
        ]);

        $this->assertSame(0, $stats['failed']);
        $this->assertSame(['https://them.example/events/2'], array_column($this->created, 'sourceUrl'));
    }

    public function testAMalformedActivityIsRefused()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Malformed activity');

        $this->service()->processActivity(null, self::FORM, self::THEM);
    }

    public function testUnknownObjectsAreLeftAlone()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', self::THEM);

        $this->service()->processActivity($this->update('https://them.example/entries/2'), self::FORM, self::THEM);

        $this->assertSame([], $this->updated);
    }

    private function sync(array $outboxItems, string $actorUri = self::THEM): array
    {
        $service = $this->service();
        $client = new class($actorUri, $outboxItems) {
            public function __construct(private string $actorUri, private array $items)
            {
            }

            public function request($method, $url, $options = [])
            {
                $body = ($url === $this->actorUri)
                    ? json_encode(['outbox' => $this->actorUri . '/outbox'])
                    : json_encode(['type' => 'OrderedCollection', 'orderedItems' => $this->items]);

                return new class($body) {
                    public function __construct(private string $body)
                    {
                    }

                    public function getContent()
                    {
                        return $this->body;
                    }
                };
            }
        };
        $property = new \ReflectionProperty($this->httpSignatureService, 'httpClient');
        $property->setValue($this->httpSignatureService, $client);

        return $service->syncActorPosts($actorUri, self::FORM);
    }

    public function testSyncCannotDeleteAnEntryThatBelongsToAnotherActor()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', 'https://them.example/actors/2');

        $stats = $this->sync([['type' => 'Delete', 'object' => 'https://them.example/entries/1']]);

        $this->assertSame([], $this->deleted, 'a cross-actor delete in the outbox must be ignored');
        $this->assertSame(0, $stats['deleted']);
    }

    public function testSyncDeletesAnEntryTheSyncedActorOwns()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', self::THEM);

        $this->sync([['type' => 'Delete', 'object' => 'https://them.example/entries/1']]);

        $this->assertSame(['FicheUne'], $this->deleted);
    }

    public function testSyncGarbageCollectionSparesAnotherActorsEntryOnTheSameHost()
    {
        $this->givenMirroredEntry('Mine', 'https://them.example/entries/1', self::THEM);
        $this->givenMirroredEntry('Theirs', 'https://them.example/entries/2', 'https://them.example/actors/2');

        $this->sync([]);

        $this->assertSame(['Mine'], $this->deleted, 'only the synced actor\'s own absent entry is swept');
    }

    public function testSyncDoesNotMirrorAnObjectFromAnotherHost()
    {
        $this->sync([['type' => 'Create', 'object' => ['id' => 'https://elsewhere.example/entries/9']]]);

        $this->assertSame([], $this->created, 'a Create whose object is on another host is skipped');
    }

    public static function untitledNotes(): array
    {
        return [
            'text of a Mastodon note' => [
                ['content' => '<p>Stop Omarchy <a href="https://stopomarchy.org/">https://stopomarchy.org/</a></p>'],
                'Stop Omarchy https://stopomarchy.org/',
            ],
            'content warning first' => [
                ['summary' => 'Politique &amp; société', 'content' => '<p>Le texte</p>'],
                'Politique & société',
            ],
            'long text cut on a word' => [
                ['content' => '<p>' . str_repeat('mot ', 40) . '</p>'],
                rtrim(str_repeat('mot ', 20)) . '…',
            ],
        ];
    }

    #[DataProvider('untitledNotes')]
    public function testANoteWithoutANameIsTitledFromItsText(array $note, string $title)
    {
        $this->convertedTitle = null;

        $this->sync([['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/1'] + $note]]);

        $this->assertSame([$title], $this->createdTitles);
    }

    private function generatedTitleTags(): array
    {
        return array_values(array_map(fn ($t) => $t['resource'], array_filter(
            $this->triples,
            fn ($t) => $t['property'] === EntryOrigin::GENERATED_TITLE_URI
        )));
    }

    public function testAGeneratedTitleIsRecordedButNeverSavedAsAField()
    {
        $this->convertedTitle = null;

        $this->sync([['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/1', 'content' => '<p>Un pouet</p>']]]);

        $this->assertSame(['Fiche1'], $this->generatedTitleTags());
        $this->assertArrayNotHasKey('__generated_title', $this->createdData[0]);
    }

    public function testANamedObjectHasNoGeneratedTitle()
    {
        $this->sync([['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/1', 'name' => 'Une fiche']]]);

        $this->assertSame([], $this->generatedTitleTags());
    }

    public function testAnUpdateThatBringsANameDropsTheGeneratedTitleMark()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/notes/1', self::THEM);
        $this->triples[] = ['resource' => 'FicheUne', 'property' => EntryOrigin::GENERATED_TITLE_URI, 'value' => '1'];

        $this->sync([['type' => 'Update', 'object' => ['id' => 'https://them.example/notes/1', 'name' => 'Un vrai titre']]]);

        $this->assertSame(['FicheUne'], $this->updated);
        $this->assertSame([], $this->generatedTitleTags());
    }

    public function testANameIsKeptAsTheTitle()
    {
        $this->sync([['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/1', 'name' => 'Une fiche', 'content' => 'Autre chose']]]);

        $this->assertSame(['Une fiche'], $this->createdTitles);
    }

    public function testAPostThatCannotBeSavedIsCountedAndTheSyncGoesOn()
    {
        $this->refusedObjects = ['https://them.example/notes/1'];

        $stats = $this->sync([
            ['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/1']],
            ['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/2']],
        ]);

        $this->assertSame(1, $stats['failed']);
        $this->assertSame(1, $stats['created']);
        $this->assertSame(['https://them.example/notes/2'], array_column($this->created, 'sourceUrl'));
    }

    private function givenFollowRequest(string $actorUri, string $followId): void
    {
        $this->triples[] = [
            'resource' => self::US . '/following',
            'property' => ActivityPubService::$AS_PREFIX . 'Follow',
            'value' => json_encode(['actor' => $actorUri, 'id' => $followId]),
        ];
    }

    private function following(): array
    {
        return array_values(array_map(fn ($t) => $t['value'], array_filter(
            $this->triples,
            fn ($t) => $t['resource'] === self::US . '/following' && $t['property'] === ActivityPubService::$AS_PREFIX . 'items'
        )));
    }

    public function testAnAcceptForAFollowNeverSentIsIgnored()
    {
        $this->service()->processActivity(['type' => 'Accept', 'actor' => self::THEM, 'object' => ['type' => 'Follow', 'id' => self::US . '/activities/1']], self::FORM, self::THEM);

        $this->assertSame([], $this->following());
    }

    public function testAnAcceptNamingTheFollowByItsIdRecordsTheFollowing()
    {
        $this->givenFollowRequest(self::THEM, self::US . '/activities/1');

        $this->service()->processActivity(['type' => 'Accept', 'actor' => self::THEM, 'object' => self::US . '/activities/1'], self::FORM, self::THEM);

        $this->assertSame([self::THEM], $this->following());
    }

    public function testAnAcceptForAnotherFollowIsIgnored()
    {
        $this->givenFollowRequest(self::THEM, self::US . '/activities/1');

        $this->service()->processActivity(['type' => 'Accept', 'actor' => self::THEM, 'object' => ['type' => 'Follow', 'id' => self::US . '/activities/2']], self::FORM, self::THEM);

        $this->assertSame([], $this->following());
    }

    public function testARejectForgetsTheFollowRequest()
    {
        $this->givenFollowRequest(self::THEM, self::US . '/activities/1');

        $this->service()->processActivity(['type' => 'Reject', 'actor' => self::THEM, 'object' => self::US . '/activities/1'], self::FORM, self::THEM);
        $this->service()->processActivity(['type' => 'Accept', 'actor' => self::THEM, 'object' => self::US . '/activities/1'], self::FORM, self::THEM);

        $this->assertSame([], $this->following());
    }

    public function testAFollowAddressedToAnotherActorIsRefused()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not addressed to this actor');

        $this->service()->processActivity(['type' => 'Follow', 'actor' => self::THEM, 'object' => 'https://us.example/actors/2'], self::FORM, self::THEM);
    }
}
