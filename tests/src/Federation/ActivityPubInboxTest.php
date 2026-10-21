<?php

namespace YesWiki\Test\Federation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Federation\Service\ActivityPubInbox;
use YesWiki\Federation\Service\ActivityPubKeyStore;
use YesWiki\Federation\Service\ActivityPubLog;
use YesWiki\Federation\Service\ActivityPubProfiles;
use YesWiki\Federation\Service\ActivityPubService;
use YesWiki\Federation\Service\EntryOrigin;
use YesWiki\Federation\Service\HttpSignatureService;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\KeyPairGenerator;
use YesWiki\Kernel\Service\SsrfUrlValidator;
use YesWiki\Kernel\Service\TripleStore;

require_once 'tests/YesWikiTestCase.php';
require_once 'src/constants.php';
require_once 'tests/src/Federation/RecordingJournal.php';

/** An activity acts on behalf of the actor the request was signed by, and on nothing that actor does not own (GHSA-rm6r-grfg-4v78). */
class ActivityPubInboxTest extends TestCase
{
    /** @var array<string, mixed> */
    private const FORM = ['id' => '1', 'activitypub_enable' => '1', 'activitypub' => ['username' => 'agenda']];
    private const THEM = 'https://them.example/actors/1';
    private const US = 'https://us.example/actors/1';
    private const PERMISSIVE = ['accept_unfollowed' => true, 'accept_non_public' => true, 'accept_replies' => true];
    private const STRICT = ['accept_unfollowed' => false, 'accept_non_public' => false, 'accept_replies' => false];
    /** @var array<string, mixed> */
    private const PUBLIC_NOTE = ['id' => 'https://them.example/notes/1', 'to' => [ActivityPubService::PUBLIC]];

    /** @var list<array<string, string>> */
    private array $triples = [];
    /** @var array<string, string> */
    private array $owners = [];
    /** @var list<string> */
    private array $updated = [];
    /** @var list<array<string, mixed>> */
    private array $updatedData = [];
    /** @var list<string> */
    private array $fetched = [];
    /** @var array<string, array<string, mixed>|null> */
    private array $documents = [];
    /** @var list<string> */
    private array $turnedDownTypes = [];
    /** @var array<string, bool> */
    private array $profile = self::PERMISSIVE;
    /** @var list<string> */
    private array $deleted = [];
    /** @var list<array<string, string>> */
    private array $created = [];
    /** @var list<array<string, mixed>> */
    private array $outbox = [];
    /** @var list<array<string, mixed>> */
    private array $createdData = [];
    private ?string $convertedTitle = 'Une fiche';
    /** @var list<string> */
    private array $refusedObjects = [];

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yeswiki-activitypub-inbox-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Storage::rootedAt($this->root)->deleteDirectory(ActivityPubKeyStore::DIRECTORY);
        foreach (['/private/keys', '/private', ''] as $directory) {
            @rmdir($this->root . $directory);
        }
    }

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
            fn ($resource, $property) => match ($property) {
                ActivityPubInbox::REMOTE_ACTOR_URI => $this->owners[$resource] ?? null,
                ActivityPubInbox::REMOTE_ACTOR_PROFILE_URI => $this->actorProfile($resource),
                default => null,
            }
        );
        $tripleStore->method('create')->willReturnCallback(function ($resource, $property, $value) {
            if ($property === ActivityPubInbox::REMOTE_ACTOR_URI) {
                $this->owners[$resource] = $value;
            } else {
                $this->triples[] = ['resource' => $resource, 'property' => $property, 'value' => $value];
            }

            return 0;
        });
        $tripleStore->method('delete')->willReturnCallback(function ($resource, $property) {
            $this->triples = array_values(array_filter(
                $this->triples,
                fn ($t) => !($t['resource'] === $resource && $t['property'] === $property)
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
            $this->createdData[] = $data;
            $this->triples[] = ['resource' => $tag, 'property' => TripleStore::SOURCE_URL_URI, 'value' => $sourceUrl];

            return ['tag' => $tag] + $data;
        });
        $entryManager->method('update')->willReturnCallback(function ($tag, $data) {
            $this->updated[] = $tag;
            $this->updatedData[] = $data;

            return [];
        });
        $entryManager->method('delete')->willReturnCallback(function ($tag) {
            $this->deleted[] = $tag;
            $this->triples = array_values(array_filter($this->triples, fn ($t) => $t['resource'] !== $tag));
            unset($this->owners[$tag]);
        });
        $entryManager->method('search')->willReturnCallback(fn () => array_map(
            fn ($resource) => ['tag' => $resource],
            array_values(array_unique(array_map(
                fn ($t) => $t['resource'],
                array_filter($this->triples, fn ($t) => $t['property'] === TripleStore::SOURCE_URL_URI)
            )))
        ));

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($entryManager);

        $semanticTransformer = $this->createStub(SemanticTransformer::class);
        $semanticTransformer->method('convertFromSemanticData')->willReturnCallback(
            fn ($form, $object) => in_array($object['type'] ?? null, $this->turnedDownTypes, true) ? null : ['bf_titre' => $this->convertedTitle]
        );

        $params = $this->createStub(ParameterBagInterface::class);
        $params->method('get')->willReturn('https://us.example/');

        $httpSignatureService = new HttpSignatureService($this->createStub(SsrfUrlValidator::class));
        $client = new MockHttpClient(function ($method, $url) {
            $this->fetched[] = $url;
            if (array_key_exists($url, $this->documents)) {
                $document = $this->documents[$url];

                return $document === null ? new MockResponse('', ['http_code' => 503]) : new MockResponse((string)json_encode($document));
            }

            return new MockResponse((string)json_encode(
                str_ends_with($url, '/outbox')
                    ? ['type' => 'OrderedCollection', 'orderedItems' => $this->outbox]
                    : ['id' => $url, 'type' => 'Person', 'name' => 'Ada <b>L.</b>', 'preferredUsername' => 'ada', 'url' => 'https://them.example/@ada', 'outbox' => $url . '/outbox', 'inbox' => $url . '/inbox']
            ));
        });
        (new \ReflectionProperty($httpSignatureService, 'httpClient'))->setValue($httpSignatureService, $client);

        $activityPub = new ActivityPubService(
            $params,
            $httpSignatureService,
            $semanticTransformer,
            $tripleStore,
            $this->createStub(SsrfUrlValidator::class),
            new ActivityPubKeyStore(Storage::rootedAt($this->root), new KeyPairGenerator()),
            new ActivityPubProfiles($container),
            new ActivityPubLog($params, new RecordingJournal()),
        );

        return new ActivityPubInbox($activityPub, $container, $httpSignatureService, $semanticTransformer, $tripleStore, new ActivityPubLog($params, new RecordingJournal()));
    }

    /** @return array<string, mixed> */
    private function form(): array
    {
        return ['activitypub' => ['username' => 'agenda'] + $this->profile] + self::FORM;
    }

    private function actorProfile(string $actorUri): ?string
    {
        foreach ($this->triples as $triple) {
            if ($triple['resource'] === $actorUri && $triple['property'] === ActivityPubInbox::REMOTE_ACTOR_PROFILE_URI) {
                return $triple['value'];
            }
        }

        return null;
    }

    private function follow(string $actorUri): void
    {
        $this->triples[] = ['resource' => self::US . '/following', 'property' => ActivityPubService::$AS_PREFIX . 'items', 'value' => $actorUri];
    }

    /** @param array<string, mixed> $object */
    private function create(array $object, string $actorUri = self::THEM): ?string
    {
        return $this->service()->processActivity(['type' => 'Create', 'actor' => $actorUri, 'object' => $object + ['type' => 'Note']], $this->form(), $actorUri);
    }

    private function givenMirroredEntry(string $tag, string $objectId, ?string $owner = null): void
    {
        $this->triples[] = ['resource' => $tag, 'property' => TripleStore::SOURCE_URL_URI, 'value' => $objectId];
        if (!is_null($owner)) {
            $this->owners[$tag] = $owner;
        }
    }

    /** @return array<string, mixed> */
    private function update(string $objectId): array
    {
        return ['type' => 'Update', 'actor' => self::THEM, 'object' => ['id' => $objectId]];
    }

    /** @return array<string, mixed> */
    private function delete(string $objectId): array
    {
        return ['type' => 'Delete', 'actor' => self::THEM, 'object' => ['id' => $objectId]];
    }

    public function testAnActivityCannotClaimAnActorTheRequestWasNotSignedFor(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', self::THEM);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('claims an actor');

        $this->service()->processActivity($this->update('https://them.example/entries/1'), $this->form(), 'https://attacker.example/actors/1');
    }

    public function testAnActorCannotUpdateAnEntryThatBelongsToAnother(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', 'https://them.example/actors/2');

        try {
            $this->service()->processActivity($this->update('https://them.example/entries/1'), $this->form(), self::THEM);
            $this->fail('the update should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('belongs to another actor', $e->getMessage());
        }
        $this->assertSame([], $this->updated);
    }

    public function testAnActorCannotDeleteAnEntryThatBelongsToAnother(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', 'https://them.example/actors/2');

        try {
            $this->service()->processActivity($this->delete('https://them.example/entries/1'), $this->form(), self::THEM);
            $this->fail('the delete should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('belongs to another actor', $e->getMessage());
        }
        $this->assertSame([], $this->deleted);
    }

    public function testTheActorAnEntryCameFromCanUpdateAndDeleteIt(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', self::THEM);
        $service = $this->service();

        $service->processActivity($this->update('https://them.example/entries/1'), $this->form(), self::THEM);
        $service->processActivity($this->delete('https://them.example/entries/1'), $this->form(), self::THEM);

        $this->assertSame(['FicheUne'], $this->updated);
        $this->assertSame(['FicheUne'], $this->deleted);
    }

    /** Entries mirrored before the owner was recorded fall back to the host their source address is on. */
    public function testAnEntryMirroredBeforeOwnersWereRecordedAnswersToItsOwnHost(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1');

        $this->service()->processActivity($this->update('https://them.example/entries/1'), $this->form(), self::THEM);

        $this->assertSame(['FicheUne'], $this->updated);
    }

    public function testAnotherHostCannotTouchAnEntryMirroredBeforeOwnersWereRecorded(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1');
        $activity = ['type' => 'Delete', 'actor' => 'https://attacker.example/actors/1', 'object' => ['id' => 'https://them.example/entries/1']];

        try {
            $this->service()->processActivity($activity, $this->form(), 'https://attacker.example/actors/1');
            $this->fail('the delete should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('another host', $e->getMessage());
        }
        $this->assertSame([], $this->deleted);
    }

    public function testCreatingAnEntryWritesDownWhoItAnswersTo(): void
    {
        $activity = [
            'type' => 'Create',
            'actor' => self::THEM,
            'object' => ['id' => 'https://them.example/entries/7', 'type' => 'Note'],
        ];

        $this->service()->processActivity($activity, $this->form(), self::THEM);

        $this->assertSame([['tag' => 'Fiche1', 'sourceUrl' => 'https://them.example/entries/7']], $this->created);
        $this->assertSame([self::THEM], array_values($this->owners));
    }

    public function testAnActorCannotBringAnObjectFromAnotherHost(): void
    {
        $activity = [
            'type' => 'Create',
            'actor' => self::THEM,
            'object' => ['id' => 'https://us.example/entries/7', 'type' => 'Note'],
        ];

        try {
            $this->service()->processActivity($activity, $this->form(), self::THEM);
            $this->fail('the creation should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('its own host', $e->getMessage());
        }
        $this->assertSame([], $this->created);
    }

    public function testAMalformedActivityIsRefused(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Malformed activity');

        $this->service()->processActivity([], $this->form(), self::THEM);
    }

    public function testUnknownObjectsAreLeftAlone(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', self::THEM);

        $this->service()->processActivity($this->update('https://them.example/entries/2'), $this->form(), self::THEM);

        $this->assertSame([], $this->updated);
    }

    /**
     * @param list<array<string, mixed>>               $outboxItems
     * @param array<string, array<string, mixed>|null> $documents
     *
     * @return array<string, int>
     */
    private function sync(array $outboxItems, string $actorUri = self::THEM, array $documents = []): array
    {
        $this->outbox = $outboxItems;
        $this->documents = $documents;

        return $this->service()->syncActorPosts($actorUri, $this->form());
    }

    public function testSyncCannotDeleteAnEntryThatBelongsToAnotherActor(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', 'https://them.example/actors/2');

        $stats = $this->sync([['type' => 'Delete', 'object' => 'https://them.example/entries/1']]);

        $this->assertSame([], $this->deleted, 'a cross-actor delete in the outbox must be ignored');
        $this->assertSame(0, $stats['deleted']);
    }

    public function testSyncDeletesAnEntryTheSyncedActorOwns(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', self::THEM);

        $this->sync([['type' => 'Delete', 'object' => 'https://them.example/entries/1']]);

        $this->assertSame(['FicheUne'], $this->deleted);
    }

    public function testSyncGarbageCollectionSparesAnotherActorsEntryOnTheSameHost(): void
    {
        $this->givenMirroredEntry('Mine', 'https://them.example/entries/1', self::THEM);
        $this->givenMirroredEntry('Theirs', 'https://them.example/entries/2', 'https://them.example/actors/2');

        $this->sync([]);

        $this->assertSame(['Mine'], $this->deleted, 'only the synced actor\'s own absent entry is swept');
    }

    public function testSyncDoesNotMirrorAnObjectFromAnotherHost(): void
    {
        $this->sync([['type' => 'Create', 'object' => ['id' => 'https://elsewhere.example/entries/9']]]);

        $this->assertSame([], $this->created, 'a Create whose object is on another host is skipped');
    }

    /** @return array<string, array{array<string, string>, string}> */
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

    /** @param array<string, string> $note */
    #[DataProvider('untitledNotes')]
    public function testANoteWithoutANameIsTitledFromItsText(array $note, string $title): void
    {
        $this->convertedTitle = null;

        $this->sync([['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/1'] + $note]]);

        $this->assertSame([$title], array_column($this->createdData, 'bf_titre'));
    }

    public function testANameIsKeptAsTheTitle(): void
    {
        $this->sync([['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/1', 'name' => 'Une fiche', 'content' => 'Autre chose']]]);

        $this->assertSame(['Une fiche'], array_column($this->createdData, 'bf_titre'));
    }

    public function testAPostThatCannotBeSavedIsCountedAndTheSyncGoesOn(): void
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

    /** @return list<string> */
    private function generatedTitleTags(): array
    {
        return array_values(array_map(fn ($t) => $t['resource'], array_filter(
            $this->triples,
            fn ($t) => $t['property'] === EntryOrigin::GENERATED_TITLE_URI
        )));
    }

    public function testAGeneratedTitleIsRecordedButNeverSavedAsAField(): void
    {
        $this->convertedTitle = null;

        $this->sync([['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/1', 'content' => '<p>Un pouet</p>']]]);

        $this->assertSame(['Fiche1'], $this->generatedTitleTags());
        $this->assertArrayNotHasKey('__generated_title', $this->createdData[0]);
    }

    public function testANamedObjectHasNoGeneratedTitle(): void
    {
        $this->sync([['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/1', 'name' => 'Une fiche']]]);

        $this->assertSame([], $this->generatedTitleTags());
    }

    public function testAnUpdateThatBringsANameDropsTheGeneratedTitleMark(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/notes/1', self::THEM);
        $this->triples[] = ['resource' => 'FicheUne', 'property' => EntryOrigin::GENERATED_TITLE_URI, 'value' => '1'];

        $this->sync([['type' => 'Update', 'object' => ['id' => 'https://them.example/notes/1', 'name' => 'Un vrai titre']]]);

        $this->assertSame(['FicheUne'], $this->updated);
        $this->assertSame([], $this->generatedTitleTags());
    }

    public function testByDefaultAnAccountTheFormDoesNotFollowCannotPublish(): void
    {
        $this->profile = self::STRICT;

        $leftOut = $this->create(self::PUBLIC_NOTE);

        $this->assertSame([], $this->created);
        $this->assertStringContainsString('does not follow', (string)$leftOut);
    }

    public function testByDefaultAFollowedAccountPublishesItsPublicAndUnlistedPosts(): void
    {
        $this->profile = self::STRICT;
        $this->follow(self::THEM);

        $this->assertNull($this->create(self::PUBLIC_NOTE));
        $this->assertNull($this->create(['id' => 'https://them.example/notes/2', 'to' => [self::THEM . '/followers'], 'cc' => 'as:Public']));

        $this->assertCount(2, $this->created);
    }

    public function testAnEventAttributedToAFollowedGroupIsPublishedWhenItsOrganizerSignsIt(): void
    {
        $this->profile = self::STRICT;
        $group = 'https://them.example/@collectif';
        $this->follow($group);

        $this->assertNull($this->create(['attributedTo' => $group] + self::PUBLIC_NOTE));
        $this->assertNull($this->create(['id' => 'https://them.example/notes/2', 'attributedTo' => [['type' => 'Group', 'id' => $group]]] + self::PUBLIC_NOTE));

        $this->assertCount(2, $this->created);
    }

    public function testAnotherHostCannotPublishInTheNameOfAFollowedGroup(): void
    {
        $this->profile = self::STRICT;
        $this->follow('https://them.example/@collectif');

        $leftOut = $this->create(['id' => 'https://attacker.example/notes/1', 'attributedTo' => 'https://them.example/@collectif', 'to' => [ActivityPubService::PUBLIC]], 'https://attacker.example/actors/1');

        $this->assertSame([], $this->created);
        $this->assertStringContainsString('does not follow', (string)$leftOut);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function leftOutByDefault(): array
    {
        return [
            'a private message' => [['id' => 'https://them.example/notes/1', 'to' => [self::US]], 'public'],
            'a followers-only post' => [['id' => 'https://them.example/notes/1', 'to' => [self::THEM . '/followers']], 'public'],
            'a reply' => [self::PUBLIC_NOTE + ['inReplyTo' => 'https://elsewhere.example/notes/9'], 'Replies'],
        ];
    }

    /** @param array<string, mixed> $object */
    #[DataProvider('leftOutByDefault')]
    public function testByDefaultPrivatePostsAndRepliesAreLeftOut(array $object, string $reason): void
    {
        $this->profile = self::STRICT;
        $this->follow(self::THEM);

        $leftOut = $this->create($object);

        $this->assertSame([], $this->created);
        $this->assertStringContainsString($reason, (string)$leftOut);
    }

    public function testEachRuleCanBeTurnedOffInTheProfile(): void
    {
        $this->assertNull($this->create(['id' => 'https://them.example/notes/1', 'to' => [self::US], 'inReplyTo' => 'https://elsewhere.example/notes/9']));

        $this->assertCount(1, $this->created);
    }

    public function testASyncSkipsPrivatePostsAndRepliesAndSweepsOnesMirroredBefore(): void
    {
        $this->profile = self::STRICT;
        $this->givenMirroredEntry('OldReply', 'https://them.example/notes/3', self::THEM);

        $this->sync([
            ['type' => 'Create', 'object' => self::PUBLIC_NOTE],
            ['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/2', 'to' => [self::THEM . '/followers']]],
            ['type' => 'Create', 'object' => ['id' => 'https://them.example/notes/3', 'to' => [ActivityPubService::PUBLIC], 'inReplyTo' => 'https://elsewhere.example/notes/9']],
        ]);

        $this->assertSame([['tag' => 'Fiche1', 'sourceUrl' => 'https://them.example/notes/1']], $this->created);
        $this->assertSame(['OldReply'], $this->deleted);
    }

    public function testAMirroredEntryStaysReadOnlyAfterItsAuthorEditsIt(): void
    {
        $this->create(self::PUBLIC_NOTE);

        $this->service()->processActivity(['type' => 'Update', 'actor' => self::THEM, 'object' => self::PUBLIC_NOTE + ['type' => 'Note']], $this->form(), self::THEM);
        $this->sync([['type' => 'Create', 'object' => self::PUBLIC_NOTE]]);

        $this->assertSame(1, $this->createdData[0]['read-only']);
        $this->assertSame([1, 1], array_column($this->updatedData, 'read-only'));
    }

    public function testTheAuthorsProfileIsKeptOnceForTheirFirstEntry(): void
    {
        $this->create(self::PUBLIC_NOTE);
        $this->create(['id' => 'https://them.example/notes/2'] + self::PUBLIC_NOTE);

        $this->assertSame([self::THEM], $this->fetched);
        $this->assertSame(['name' => 'Ada L.', 'username' => 'ada', 'url' => 'https://them.example/@ada'], json_decode((string)$this->actorProfile(self::THEM), true));
    }

    public function testAnUpdateOfTheActorRefreshesTheirProfileWithoutTouchingEntries(): void
    {
        $leftOut = $this->service()->processActivity(['type' => 'Update', 'actor' => self::THEM, 'object' => ['id' => self::THEM, 'type' => 'Person', 'name' => 'Ada Lovelace', 'preferredUsername' => 'ada']], $this->form(), self::THEM);

        $this->assertNull($leftOut);
        $this->assertSame('Ada Lovelace', json_decode((string)$this->actorProfile(self::THEM), true)['name']);
        $this->assertSame([], $this->updated);
    }

    public function testACreateForAnObjectAlreadyMirroredUpdatesItInsteadOfDuplicatingIt(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/events/1', self::THEM);

        $this->service()->processActivity(['type' => 'Create', 'actor' => self::THEM, 'object' => ['id' => 'https://them.example/events/1', 'type' => 'Event']], $this->form(), self::THEM);

        $this->assertSame([], $this->created);
        $this->assertSame(['FicheUne'], $this->updated);
    }

    public function testACreateForAnotherActorsMirroredObjectIsRefused(): void
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/events/1', 'https://them.example/actors/2');

        try {
            $this->service()->processActivity(['type' => 'Create', 'actor' => self::THEM, 'object' => ['id' => 'https://them.example/events/1', 'type' => 'Event']], $this->form(), self::THEM);
            $this->fail('the create should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('belongs to another actor', $e->getMessage());
        }
        $this->assertSame([], $this->updated);
    }

    public function testAnObjectTheReverseTemplateTurnsDownIsNotMirrored(): void
    {
        $this->turnedDownTypes = ['Note'];
        $this->givenMirroredEntry('FicheUne', 'https://them.example/events/1', self::THEM);
        $service = $this->service();

        $service->processActivity(['type' => 'Create', 'actor' => self::THEM, 'object' => ['id' => 'https://them.example/comments/1', 'type' => 'Note']], $this->form(), self::THEM);
        $service->processActivity(['type' => 'Update', 'actor' => self::THEM, 'object' => ['id' => 'https://them.example/events/1', 'type' => 'Note']], $this->form(), self::THEM);

        $this->assertSame([], $this->created);
        $this->assertSame([], $this->updated);
    }

    public function testASyncSkipsWhatTheReverseTemplateTurnsDownWithoutCountingItAsAFailure(): void
    {
        $this->turnedDownTypes = ['Note'];

        $stats = $this->sync([
            ['type' => 'Create', 'object' => ['id' => 'https://them.example/comments/1', 'type' => 'Note']],
            ['type' => 'Create', 'object' => ['id' => 'https://them.example/events/2', 'type' => 'Event']],
        ]);

        $this->assertSame(0, $stats['failed']);
        $this->assertSame(['https://them.example/events/2'], array_column($this->created, 'sourceUrl'));
    }

    public function testASyncFetchesObjectsTheOutboxOnlyLinksTo(): void
    {
        $note = 'https://them.example/statuses/1';
        $stats = $this->sync(
            [['type' => 'Create', 'object' => $note], ['type' => 'Announce', 'object' => 'https://them.example/statuses/2'], ['type' => 'Create', 'object' => 'https://elsewhere.example/statuses/3']],
            self::THEM,
            [$note => ['id' => $note, 'type' => 'Note', 'to' => [ActivityPubService::PUBLIC]]]
        );

        $this->assertSame([['tag' => 'Fiche1', 'sourceUrl' => $note]], $this->created);
        $this->assertSame(1, $stats['created']);
        $this->assertNotContains('https://elsewhere.example/statuses/3', $this->fetched);
    }

    public function testAnObjectThatCannotBeFetchedIsNotSweptAway(): void
    {
        $note = 'https://them.example/statuses/1';
        $this->givenMirroredEntry('Mirrored', $note, self::THEM);

        $stats = $this->sync([['type' => 'Create', 'object' => $note]], self::THEM, [$note => null]);

        $this->assertSame([], $this->deleted);
        $this->assertSame(1, $stats['failed']);
    }
}
