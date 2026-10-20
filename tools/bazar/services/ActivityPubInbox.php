<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Core\Service\TripleStore;

/**
 * Brings remote ActivityPub activity into the wiki: inbox deliveries and outbox syncs, mirrored as entries.
 */
class ActivityPubInbox
{
    public const REMOTE_ACTOR_URI = 'http://outils-reseaux.org/_vocabulary/remoteActor';
    public const REMOTE_ACTOR_PROFILE_URI = 'http://outils-reseaux.org/_vocabulary/remoteActorProfile';
    private const ACTOR_TYPES = ['Person', 'Service', 'Application', 'Group', 'Organization'];
    private const GENERATED_TITLE_LENGTH = 80;
    private const GENERATED_TITLE_MARKER = '__generated_title';

    protected $activityPubService;
    protected $entryManager;
    protected $httpSignatureService;
    protected $semanticTransformer;
    protected $tripleStore;
    protected $log;

    public function __construct(ActivityPubService $activityPubService, EntryManager $entryManager, HttpSignatureService $httpSignatureService, SemanticTransformer $semanticTransformer, TripleStore $tripleStore, ActivityPubLog $log)
    {
        $this->log = $log;
        $this->activityPubService = $activityPubService;
        $this->entryManager = $entryManager;
        $this->httpSignatureService = $httpSignatureService;
        $this->semanticTransformer = $semanticTransformer;
        $this->tripleStore = $tripleStore;
    }

    /**
     * Acts on an activity delivered to the inbox, refusing what the signing actor may not do; returns why an object was left out, or null.
     */
    public function processActivity($activity, $form, string $verifiedActor): ?string
    {
        if (!is_array($activity) || empty($activity['type'])) {
            throw new \Exception('Malformed activity');
        }
        if (($activity['actor'] ?? null) !== $verifiedActor) {
            throw new \Exception('The activity claims an actor the request was not signed for');
        }

        switch ($activity['type']) {
            case 'Accept':
                if ($this->isFollow($activity['object'] ?? null)) {
                    $this->activityPubService->acceptFollow($form, $activity['actor'], $this->objectId($activity['object']));
                }
                break;

            case 'Reject':
                if ($this->isFollow($activity['object'] ?? null)) {
                    $this->activityPubService->rejectFollow($form, $activity['actor']);
                }
                break;

            case 'Follow':
                if ($this->objectId($activity['object'] ?? null) !== $this->activityPubService->getFormActorUri($form)) {
                    throw new \Exception('This Follow is not addressed to this actor');
                }
                $this->activityPubService->receiveFollow($form, $activity);

                break;

            case 'Undo':
                if (($activity['object']['type'] ?? null) === 'Follow') {
                    $this->activityPubService->undoFollow($form, $activity['actor']);
                }

                break;

            case 'Create':
            case 'Update':
                $object = $activity['object'] ?? null;
                if (!is_array($object) || empty($object['id']) || !is_string($object['id'])) {
                    throw new \Exception('A ' . $activity['type'] . ' needs an object with an id');
                }
                if ($object['id'] === $verifiedActor && in_array($object['type'] ?? null, self::ACTOR_TYPES, true)) {
                    $this->rememberActor($verifiedActor, $form, $object);

                    return null;
                }

                return $this->mirror($form, $object, $verifiedActor, $activity['type'] === 'Create');

            case 'Delete':
                $objectId = \is_array($activity['object']) ? ($activity['object']['id'] ?? null) : $activity['object'];
                if ($objectId) {
                    $triples = $this->tripleStore->getMatching(null, TripleStore::SOURCE_URL_URI, $objectId, '=', '=', '=');
                    if (!empty($triples)) {
                        $tag = $triples[0]['resource'];
                        $this->assertOwns($verifiedActor, $tag, $objectId);
                        $this->entryManager->delete($tag, true);
                    }
                }
                break;
        }

        return null;
    }

    /**
     * Creates or updates the entry mirroring an object sent to the inbox; a Create of an object already mirrored updates it. Returns why a new object was left out, or null.
     */
    protected function mirror(array $form, array $object, string $verifiedActor, bool $mayCreate): ?string
    {
        $triples = $this->tripleStore->getMatching(null, TripleStore::SOURCE_URL_URI, $object['id'], '=', '=', '=');
        if (empty($triples)) {
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
            $created = $this->entryManager->create($form['bn_id_nature'], $this->withoutMarker($entry), false, $object['id']);
            $this->rememberOwner($created['id_fiche'] ?? null, $verifiedActor);
            $this->rememberGeneratedTitle($created['id_fiche'] ?? null, $entry);
            $this->rememberActor($verifiedActor, $form);

            return null;
        }
        $tag = $triples[0]['resource'];
        $this->assertOwns($verifiedActor, $tag, $object['id']);
        $entry = $this->entryFromObject($form, $object);
        if ($entry === null) {
            return null;
        }
        $this->entryManager->update($tag, $this->withoutMarker($entry), false);
        $this->rememberGeneratedTitle($tag, $entry);

        return null;
    }

    /**
     * Why the form will not publish what this actor sends: by default only what a followed account signs, or attributes to a followed account on its own host, as a Mobilizon organizer does for its group.
     */
    protected function authorRefusal(array $form, string $actorUri, array $object): ?string
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
     * @return string[]
     */
    private function attributedTo(array $object): array
    {
        $authors = $object['attributedTo'] ?? [];
        $authors = is_array($authors) && !array_is_list($authors) ? [$authors] : (array)$authors;

        return array_values(array_filter(array_map(fn ($author) => is_array($author) ? ($author['id'] ?? null) : $author, $authors), 'is_string'));
    }

    /**
     * Why the form will not publish this object: by default neither replies nor objects that are not public.
     */
    protected function objectRefusal(array $form, array $object): ?string
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
     * Whether an Accept or Reject is about a Follow: an embedded Follow, or a bare id when the sender does not embed it.
     */
    protected function isFollow($object): bool
    {
        return is_string($object) || (is_array($object) && ($object['type'] ?? null) === 'Follow');
    }

    protected function objectId($object): ?string
    {
        if (is_array($object)) {
            $object = $object['id'] ?? null;
        }

        return is_string($object) ? $object : null;
    }

    /**
     * Records which remote actor a mirrored entry answers to.
     */
    protected function rememberOwner(?string $tag, ?string $actorUri): void
    {
        if (empty($tag) || empty($actorUri)) {
            return;
        }
        $this->tripleStore->create($tag, self::REMOTE_ACTOR_URI, $actorUri, '', '');
    }

    /**
     * Keeps the display name, username and page of a remote account, fetching it once when no document is given; a fetch that fails leaves it unknown.
     */
    protected function rememberActor(string $actorUri, array $form, ?array $document = null): void
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
        $this->tripleStore->create($actorUri, self::REMOTE_ACTOR_PROFILE_URI, json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), '', '');
    }

    /**
     * Whether an actor may act on a mirrored entry: it recorded the owner, or no owner is recorded and the object is on its host.
     */
    protected function isOwnedBy(string $verifiedActor, string $tag, string $objectId): bool
    {
        $owner = $this->tripleStore->getOne($tag, self::REMOTE_ACTOR_URI, '', '');
        if (!empty($owner)) {
            return $owner === $verifiedActor;
        }

        return $this->httpSignatureService->sameHost($verifiedActor, $objectId);
    }

    /**
     * Refuses an actor that does not own the mirrored entry, or is not on its host when no owner was recorded.
     */
    protected function assertOwns(string $verifiedActor, string $tag, string $objectId): void
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

    public function syncActorPosts(string $actorUri, array $form): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'failed' => 0];

        $actor = $this->activityPubService->fetch($actorUri, $form);
        $this->rememberActor($actorUri, $form, $actor);

        if (empty($actor['outbox'])) {
            return $stats;
        }

        $remoteItems = $this->fetchAllOutboxItems($actor['outbox'], $form);

        $remoteObjectIds = [];

        foreach ($remoteItems as $item) {
            $type = $item['type'] ?? null;
            $object = $item['object'] ?? null;

            if ($type === 'Delete') {
                $objectId = is_array($object) ? ($object['id'] ?? null) : $object;
                if ($objectId) {
                    $existingTriples = $this->tripleStore->getMatching(null, TripleStore::SOURCE_URL_URI, $objectId, '=', '=', '=');
                    if (!empty($existingTriples) && $this->isOwnedBy($actorUri, $existingTriples[0]['resource'], $objectId)) {
                        $this->entryManager->delete($existingTriples[0]['resource'], true);
                        $stats['deleted']++;
                    }
                }
                continue;
            }

            if (!$object || !isset($object['id']) || $this->objectRefusal($form, $object) !== null) {
                continue;
            }

            $remoteObjectIds[] = $object['id'];

            $existingTriples = $this->tripleStore->getMatching(null, TripleStore::SOURCE_URL_URI, $object['id'], '=', '=', '=');

            try {
                $this->syncItem($type, $object, $existingTriples, $actorUri, $form, $stats);
            } catch (\Exception $e) {
                $stats['failed']++;
                $this->log->syncFailed($form['bn_id_nature'] ?? '', $actorUri, $object, $e->getMessage());
            }
        }

        $localEntries = $this->entryManager->search(['idtypeannonce' => $form['bn_id_nature']]);

        foreach ($localEntries as $entry) {
            $tag = $entry['id_fiche'];
            $sourceTriples = $this->tripleStore->getMatching($tag, TripleStore::SOURCE_URL_URI, null, '=', '=', '');

            if (!empty($sourceTriples)) {
                $sourceUrl = $sourceTriples[0]['value'];
                if (!in_array($sourceUrl, $remoteObjectIds) && $this->isOwnedBy($actorUri, $tag, $sourceUrl)) {
                    $this->entryManager->delete($tag, true);
                    $stats['deleted']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Creates or updates the entry mirroring one outbox item, counting what it did in $stats.
     */
    private function syncItem(?string $type, array $object, array $existingTriples, string $actorUri, array $form, array &$stats): void
    {
        if ($type === 'Create' && empty($existingTriples)) {
            if (!$this->httpSignatureService->sameHost($actorUri, $object['id'])) {
                return;
            }
            $entry = $this->entryFromObject($form, $object);
            if ($entry === null) {
                return;
            }
            $created = $this->entryManager->create($form['bn_id_nature'], $this->withoutMarker($entry), false, $object['id']);
            $this->rememberOwner($created['id_fiche'] ?? null, $actorUri);
            $this->rememberGeneratedTitle($created['id_fiche'] ?? null, $entry);
            $stats['created']++;

            return;
        }
        if (!in_array($type, ['Create', 'Update'], true) || empty($existingTriples)) {
            return;
        }
        $tag = $existingTriples[0]['resource'];
        if (!$this->isOwnedBy($actorUri, $tag, $object['id'])) {
            return;
        }
        $entry = $this->entryFromObject($form, $object);
        if ($entry === null) {
            return;
        }
        $this->entryManager->update($tag, $this->withoutMarker($entry), false);
        $this->rememberGeneratedTitle($tag, $entry);
        $stats['updated']++;
    }

    /**
     * Turns a remote object into the fields of a read-only entry, titling it from its summary or text when it has no name, as Mastodon notes do; null when the form's reverse template turns it down.
     */
    protected function entryFromObject(array $form, array $object): ?array
    {
        $entry = $this->semanticTransformer->convertFromSemanticData($form['bn_id_nature'], $object);
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

    private function withoutMarker(array $entry): array
    {
        unset($entry[self::GENERATED_TITLE_MARKER]);

        return $entry;
    }

    /**
     * Records whether a mirrored entry's title was made up from its text, so lists can show the text instead.
     */
    protected function rememberGeneratedTitle(?string $tag, array $entry): void
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
     */
    protected function titleFromObject(array $object): string
    {
        foreach (['summary', 'content'] as $key) {
            $text = $this->plainText($object[$key] ?? '');
            if ($text !== '') {
                return $this->shorten($text, self::GENERATED_TITLE_LENGTH);
            }
        }
        $published = strtotime((string)($object['published'] ?? '')) ?: time();

        return sprintf(_t('BAZ_ACTIVITYPUB_UNTITLED_NOTE'), date('Y-m-d H:i', $published));
    }

    private function plainText($html): string
    {
        if (!is_string($html)) {
            return '';
        }
        $text = preg_replace('#<br\s*/?>|</p>|</li>#i', ' ', $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
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

    private function fetchAllOutboxItems(string $outboxUrl, array $form): array
    {
        $items = [];
        $url = $outboxUrl;
        $maxPages = 20;

        for ($page = 0; $url && $page < $maxPages; $page++) {
            $data = $this->activityPubService->fetch($url, $form);
            $type = $data['type'] ?? null;

            if ($type === 'OrderedCollection' && isset($data['first']) && !isset($data['orderedItems'])) {
                $url = is_string($data['first']) ? $data['first'] : ($data['first']['id'] ?? null);
                continue;
            }

            if (isset($data['orderedItems'])) {
                $items = array_merge($items, $data['orderedItems']);
            } elseif (isset($data['items'])) {
                $items = array_merge($items, $data['items']);
            }

            if (isset($data['next'])) {
                $url = is_string($data['next']) ? $data['next'] : ($data['next']['id'] ?? null);
            } else {
                break;
            }
        }

        return $items;
    }
}
