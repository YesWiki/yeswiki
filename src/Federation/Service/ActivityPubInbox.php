<?php

namespace YesWiki\Federation\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Kernel\Service\TripleStore;

/** Brings remote ActivityPub activity into the wiki: inbox deliveries and outbox syncs, mirrored as entries. */
class ActivityPubInbox
{
    public const REMOTE_ACTOR_URI = 'http://outils-reseaux.org/_vocabulary/remoteActor';
    public const REMOTE_ACTOR_PROFILE_URI = 'http://outils-reseaux.org/_vocabulary/remoteActorProfile';
    private const ACTOR_TYPES = ['Person', 'Service', 'Application', 'Group', 'Organization'];
    private const GENERATED_TITLE_LENGTH = 80;
    private const GENERATED_TITLE_MARKER = '__generated_title';

    public function __construct(
        private readonly ActivityPubService $activityPubService,
        private readonly ContainerInterface $container,
        private readonly HttpSignatureService $httpSignatureService,
        private readonly SemanticTransformer $semanticTransformer,
        private readonly TripleStore $tripleStore,
        private readonly ActivityPubLog $log,
    ) {
    }

    /**
     * Acts on an activity delivered to the inbox, on behalf of the actor that signed for it; returns why an object was left out, or null.
     *
     * @param array<string, mixed> $activity
     * @param array<string, mixed> $form
     */
    public function processActivity(array $activity, array $form, string $verifiedActor): ?string
    {
        if (empty($activity['type'])) {
            throw new \Exception('Malformed activity');
        }
        if (($activity['actor'] ?? null) !== $verifiedActor) {
            throw new \Exception('The activity claims an actor the request was not signed for');
        }

        switch ($activity['type']) {
            case 'Accept':
                if ($this->isFollow($activity['object'] ?? null)) {
                    $this->activityPubService->acceptFollow($form, $verifiedActor, $this->objectId($activity['object']));
                }
                break;

            case 'Reject':
                if ($this->isFollow($activity['object'] ?? null)) {
                    $this->activityPubService->rejectFollow($form, $verifiedActor);
                }
                break;

            case 'Follow':
                if ($this->objectId($activity['object'] ?? null) !== $this->activityPubService->getFormActorUri($form)) {
                    throw new \Exception('This Follow is not addressed to this actor');
                }
                $this->activityPubService->receiveFollow($form, $activity);
                break;

            case 'Undo':
                if (is_array($activity['object'] ?? null) && ($activity['object']['type'] ?? null) === 'Follow') {
                    $this->activityPubService->undoFollow($form, $verifiedActor);
                }
                break;

            case 'Create':
            case 'Update':
                $object = $activity['object'] ?? null;
                if (!is_array($object) || !is_string($object['id'] ?? null) || $object['id'] === '') {
                    throw new \Exception('A ' . $activity['type'] . ' needs an object with an id');
                }
                if ($object['id'] === $verifiedActor && in_array($object['type'] ?? null, self::ACTOR_TYPES, true)) {
                    $this->rememberActor($verifiedActor, $form, $object);

                    return null;
                }

                return $this->mirror($form, $object, $verifiedActor, $activity['type'] === 'Create');

            case 'Delete':
                $objectId = $this->objectId($activity['object'] ?? null);
                $tag = $objectId === null ? null : $this->mirrorOf($objectId);
                if ($tag !== null) {
                    $this->assertOwns($verifiedActor, $tag, (string)$objectId);
                    $this->entryManager()->delete($tag, true);
                }
                break;
        }

        return null;
    }

    /**
     * Creates or updates the entry mirroring an object sent to the inbox; a Create of an object already mirrored updates it. Returns why a new object was left out, or null.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $object
     */
    private function mirror(array $form, array $object, string $verifiedActor, bool $mayCreate): ?string
    {
        $tag = $this->mirrorOf($object['id']);
        if ($tag === null) {
            if (!$mayCreate) {
                return null;
            }
            if (!$this->httpSignatureService->sameHost($verifiedActor, $object['id'])) {
                throw new \Exception('An actor can only bring objects from its own host');
            }
            $refusal = $this->authorRefusal($form, $verifiedActor, $object) ?? $this->objectRefusal($form, $object);
            if ($refusal !== null) {
                return $refusal;
            }
            $entry = $this->entryFromObject($form, $object);
            if ($entry === null) {
                return null;
            }
            $created = $this->entryManager()->create($form['id'], $this->withoutMarker($entry), false, $object['id']);
            $this->rememberOwner($created['tag'] ?? null, $verifiedActor);
            $this->rememberGeneratedTitle($created['tag'] ?? null, $entry);
            $this->rememberActor($verifiedActor, $form);

            return null;
        }
        $this->assertOwns($verifiedActor, $tag, $object['id']);
        $entry = $this->entryFromObject($form, $object);
        if ($entry === null) {
            return null;
        }
        $this->entryManager()->update($tag, $this->withoutMarker($entry), false);
        $this->rememberGeneratedTitle($tag, $entry);

        return null;
    }

    /**
     * Why the form will not publish what this actor sends: by default only what a followed account signs, or attributes to a followed account on its own host, as a Mobilizon organizer does for its group.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $object
     */
    private function authorRefusal(array $form, string $actorUri, array $object): ?string
    {
        if ($this->activityPubService->profile($form)['accept_unfollowed']) {
            return null;
        }
        $following = $this->activityPubService->getFollowing($form);
        if (in_array($actorUri, $following, true)) {
            return null;
        }
        foreach ($this->attributedTo($object) as $author) {
            if (in_array($author, $following, true) && $this->httpSignatureService->sameHost($actorUri, $author)) {
                return null;
            }
        }

        return 'This form does not follow ' . $actorUri;
    }

    /**
     * The actor ids an object is attributed to, whether given as a string, an object or a list of either.
     *
     * @param array<string, mixed> $object
     *
     * @return list<string>
     */
    private function attributedTo(array $object): array
    {
        $authors = $object['attributedTo'] ?? [];
        $authors = is_array($authors) && !array_is_list($authors) ? [$authors] : (array)$authors;

        return array_values(array_filter(array_map(fn ($author) => is_array($author) ? ($author['id'] ?? null) : $author, $authors), 'is_string'));
    }

    /**
     * Why the form will not publish this object: by default neither replies nor objects that are not public.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $object
     */
    private function objectRefusal(array $form, array $object): ?string
    {
        $profile = $this->activityPubService->profile($form);
        if (!$profile['accept_non_public'] && !$this->isPublic($object)) {
            return 'Only public objects are published';
        }
        if (!$profile['accept_replies'] && !empty($object['inReplyTo'])) {
            return 'Replies are not published';
        }

        return null;
    }

    /** @param array<string, mixed> $object */
    private function isPublic(array $object): bool
    {
        foreach (['to', 'cc'] as $key) {
            foreach ((array)($object[$key] ?? []) as $audience) {
                if (in_array($audience, [ActivityPubService::PUBLIC, 'as:Public', 'Public'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Keeps the display name, username and page of a remote account, fetching it once when no document is given; a fetch that fails leaves it unknown.
     *
     * @param array<string, mixed>      $form
     * @param array<string, mixed>|null $document
     */
    private function rememberActor(string $actorUri, array $form, ?array $document = null): void
    {
        if ($document === null) {
            if ($this->tripleStore->getOne($actorUri, self::REMOTE_ACTOR_PROFILE_URI, '', '') !== null) {
                return;
            }
            try {
                $document = $this->activityPubService->fetch($actorUri, $form);
            } catch (\Throwable $e) {
                return;
            }
        }
        $text = fn ($value) => is_string($value) ? mb_substr(trim(strip_tags($value)), 0, 200) : '';
        $profile = [
            'name' => $text($document['name'] ?? null),
            'username' => $text($document['preferredUsername'] ?? null),
            'url' => is_string($document['url'] ?? null) && preg_match('~^https?://~i', $document['url']) ? $document['url'] : '',
        ];
        $this->tripleStore->delete($actorUri, self::REMOTE_ACTOR_PROFILE_URI, null, '', '');
        $this->tripleStore->create($actorUri, self::REMOTE_ACTOR_PROFILE_URI, (string)json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), '', '');
    }

    /**
     * Mirrors every entry an actor's outbox holds and drops the mirrored ones it no longer does.
     *
     * @param array<string, mixed> $form
     *
     * @return array{created: int, updated: int, deleted: int, failed: int}
     */
    public function syncActorPosts(string $actorUri, array $form): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'failed' => 0];
        $entryManager = $this->entryManager();

        $actor = $this->activityPubService->fetch($actorUri, $form);
        $this->rememberActor($actorUri, $form, $actor);
        if (empty($actor['outbox']) || !is_string($actor['outbox'])) {
            return $stats;
        }

        $remoteObjectIds = [];

        foreach ($this->fetchAllOutboxItems($actor['outbox'], $form) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = $item['type'] ?? null;
            $object = $item['object'] ?? null;

            if ($type === 'Delete') {
                $objectId = $this->objectId($object);
                $tag = $objectId === null ? null : $this->mirrorOf($objectId);
                if ($tag !== null && $this->isOwnedBy($actorUri, $tag, (string)$objectId)) {
                    $entryManager->delete($tag, true);
                    $stats['deleted']++;
                }
                continue;
            }

            if (is_string($object) && in_array($type, ['Create', 'Update'], true)) {
                if (!$this->httpSignatureService->sameHost($actorUri, $object)) {
                    continue;
                }
                $objectId = $object;
                try {
                    $object = $this->activityPubService->fetch($objectId, $form);
                } catch (\Throwable $e) {
                    $remoteObjectIds[] = $objectId;
                    $stats['failed']++;
                    $this->log->syncFailed((string)$form['id'], $actorUri, $objectId, $e->getMessage());
                    continue;
                }
                if (($object['id'] ?? null) !== $objectId) {
                    continue;
                }
            }

            if (!is_array($object) || !is_string($object['id'] ?? null) || $this->objectRefusal($form, $object) !== null) {
                continue;
            }

            $remoteObjectIds[] = $object['id'];

            try {
                $this->syncItem(is_string($type) ? $type : null, $object, $actorUri, $form, $stats);
            } catch (\Exception $e) {
                $stats['failed']++;
                $this->log->syncFailed((string)$form['id'], $actorUri, $object, $e->getMessage());
            }
        }

        foreach ($entryManager->search(['id' => $form['id']]) as $entry) {
            $tag = $entry['tag'];
            $sourceTriples = $this->tripleStore->getMatching($tag, TripleStore::SOURCE_URL_URI, null, '=', '=', '');
            if (empty($sourceTriples)) {
                continue;
            }
            $sourceUrl = $sourceTriples[0]['value'];
            if (!in_array($sourceUrl, $remoteObjectIds, true) && $this->isOwnedBy($actorUri, $tag, $sourceUrl)) {
                $entryManager->delete($tag, true);
                $stats['deleted']++;
            }
        }

        return $stats;
    }

    /**
     * Creates or updates the entry mirroring one outbox item, counting what it did in $stats.
     *
     * @param array<string, mixed>                                         $object
     * @param array<string, mixed>                                         $form
     * @param array{created: int, updated: int, deleted: int, failed: int} $stats
     */
    private function syncItem(?string $type, array $object, string $actorUri, array $form, array &$stats): void
    {
        $tag = $this->mirrorOf($object['id']);
        if ($type === 'Create' && $tag === null) {
            if (!$this->httpSignatureService->sameHost($actorUri, $object['id'])) {
                return;
            }
            $entry = $this->entryFromObject($form, $object);
            if ($entry === null) {
                return;
            }
            $created = $this->entryManager()->create($form['id'], $this->withoutMarker($entry), false, $object['id']);
            $this->rememberOwner($created['tag'] ?? null, $actorUri);
            $this->rememberGeneratedTitle($created['tag'] ?? null, $entry);
            $stats['created']++;

            return;
        }
        if (!in_array($type, ['Create', 'Update'], true) || $tag === null || !$this->isOwnedBy($actorUri, $tag, $object['id'])) {
            return;
        }
        $entry = $this->entryFromObject($form, $object);
        if ($entry === null) {
            return;
        }
        $this->entryManager()->update($tag, $this->withoutMarker($entry), false);
        $this->rememberGeneratedTitle($tag, $entry);
        $stats['updated']++;
    }

    /**
     * Turns a remote object into the fields of a read-only entry, titling it from its summary or text when it has no name, as Mastodon notes do; null when the form's reverse template turns it down.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $object
     *
     * @return array<string, mixed>|null
     */
    private function entryFromObject(array $form, array $object): ?array
    {
        $entry = $this->semanticTransformer->convertFromSemanticData($form, $object);
        if ($entry === null) {
            return null;
        }
        $entry['read-only'] = 1;
        if ($this->plainText($entry['bf_titre'] ?? '') === '') {
            $entry['bf_titre'] = $this->titleFromObject($object);
            $entry[self::GENERATED_TITLE_MARKER] = true;
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function withoutMarker(array $entry): array
    {
        unset($entry[self::GENERATED_TITLE_MARKER]);

        return $entry;
    }

    /**
     * Records whether a mirrored entry's title was made up from its text, so lists can show the text instead.
     *
     * @param array<string, mixed> $entry
     */
    private function rememberGeneratedTitle(?string $tag, array $entry): void
    {
        if (empty($tag)) {
            return;
        }
        $this->tripleStore->delete($tag, EntryOrigin::GENERATED_TITLE_URI, null, '', '');
        if (!empty($entry[self::GENERATED_TITLE_MARKER])) {
            $this->tripleStore->create($tag, EntryOrigin::GENERATED_TITLE_URI, '1', '', '');
        }
    }

    /**
     * A short title from the object's content warning or the start of its text, or else its publication date.
     *
     * @param array<string, mixed> $object
     */
    private function titleFromObject(array $object): string
    {
        foreach (['summary', 'content'] as $key) {
            $text = $this->plainText($object[$key] ?? '');
            if ($text !== '') {
                return $this->shorten($text, self::GENERATED_TITLE_LENGTH);
            }
        }
        $published = strtotime(is_string($object['published'] ?? null) ? $object['published'] : '') ?: time();

        return sprintf(_t('BAZ_ACTIVITYPUB_UNTITLED_NOTE'), date('Y-m-d H:i', $published));
    }

    private function plainText(mixed $html): string
    {
        if (!is_string($html)) {
            return '';
        }
        $text = (string)preg_replace('#<br\s*/?>|</p>|</li>#i', ' ', $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    private function shorten(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > $length / 2) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, ' ,;:.-') . '…';
    }

    /** Whether an Accept or Reject is about a Follow: an embedded Follow, or a bare id when the sender does not embed it. */
    private function isFollow(mixed $object): bool
    {
        return is_string($object) || (is_array($object) && ($object['type'] ?? null) === 'Follow');
    }

    private function objectId(mixed $object): ?string
    {
        if (is_array($object)) {
            $object = $object['id'] ?? null;
        }

        return is_string($object) && $object !== '' ? $object : null;
    }

    /** The tag of the entry mirroring a remote object, or null. */
    private function mirrorOf(string $objectId): ?string
    {
        $triples = $this->tripleStore->getMatching(null, TripleStore::SOURCE_URL_URI, $objectId, '=', '=', '=');

        return empty($triples) ? null : (string)$triples[0]['resource'];
    }

    /** Writes down which actor a mirrored entry answers to, so a later Update or Delete can be checked against it. */
    private function rememberOwner(?string $tag, ?string $actorUri): void
    {
        if (empty($tag) || empty($actorUri)) {
            return;
        }
        $this->tripleStore->create($tag, self::REMOTE_ACTOR_URI, $actorUri, '', '');
    }

    /** Refuses an actor that does not own the mirrored entry, or is not on its host when no owner was recorded. */
    private function assertOwns(string $verifiedActor, string $tag, string $objectId): void
    {
        $owner = $this->tripleStore->getOne($tag, self::REMOTE_ACTOR_URI, '', '');
        if (!empty($owner)) {
            if ($owner !== $verifiedActor) {
                throw new \Exception('This entry belongs to another actor');
            }

            return;
        }
        if (!$this->httpSignatureService->sameHost($verifiedActor, $objectId)) {
            throw new \Exception('This entry comes from another host');
        }
    }

    /** The same ownership rule as assertOwns(), as a question, so a sync can skip what an actor may not touch. */
    private function isOwnedBy(string $verifiedActor, string $tag, string $objectId): bool
    {
        $owner = $this->tripleStore->getOne($tag, self::REMOTE_ACTOR_URI, '', '');
        if (!empty($owner)) {
            return $owner === $verifiedActor;
        }

        return $this->httpSignatureService->sameHost($verifiedActor, $objectId);
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return list<mixed>
     */
    private function fetchAllOutboxItems(string $outboxUrl, array $form): array
    {
        $items = [];
        $url = $outboxUrl;

        for ($page = 0; is_string($url) && $url !== '' && $page < 20; $page++) {
            $data = $this->activityPubService->fetch($url, $form);

            if (($data['type'] ?? null) === 'OrderedCollection' && isset($data['first']) && !isset($data['orderedItems'])) {
                $url = is_string($data['first']) ? $data['first'] : ($data['first']['id'] ?? null);
                continue;
            }

            $pageItems = $data['orderedItems'] ?? $data['items'] ?? [];
            $items = array_merge($items, is_array($pageItems) ? array_values($pageItems) : []);

            if (!isset($data['next'])) {
                break;
            }
            $url = is_string($data['next']) ? $data['next'] : ($data['next']['id'] ?? null);
        }

        return $items;
    }

    private function entryManager(): EntryManager
    {
        $entryManager = $this->container->get(EntryManager::class);
        if (!$entryManager instanceof EntryManager) {
            throw new \LogicException('No EntryManager in the container');
        }

        return $entryManager;
    }
}
