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
    private const GENERATED_TITLE_LENGTH = 80;
    private const GENERATED_TITLE_MARKER = '__generated_title';

    public function __construct(
        private readonly ActivityPubService $activityPubService,
        private readonly ContainerInterface $container,
        private readonly HttpSignatureService $httpSignatureService,
        private readonly SemanticTransformer $semanticTransformer,
        private readonly TripleStore $tripleStore,
    ) {
    }

    /**
     * Acts on an activity delivered to the inbox, on behalf of the actor that signed for it.
     *
     * @param array<string, mixed> $activity
     * @param array<string, mixed> $form
     */
    public function processActivity(array $activity, array $form, string $verifiedActor): void
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
                $object = is_array($activity['object'] ?? null) ? $activity['object'] : [];
                if (!$this->httpSignatureService->sameHost($verifiedActor, is_string($object['id'] ?? null) ? $object['id'] : '')) {
                    throw new \Exception('An actor can only bring objects from its own host');
                }
                $entry = $this->entryFromObject($form, $object);
                $entry['read-only'] = 1;
                $created = $this->entryManager()->create($form['id'], $this->withoutMarker($entry), false, $object['id']);
                $this->rememberOwner($created['tag'] ?? null, $verifiedActor);
                $this->rememberGeneratedTitle($created['tag'] ?? null, $entry);
                break;

            case 'Update':
                $object = is_array($activity['object'] ?? null) ? $activity['object'] : [];
                $objectId = $this->objectId($object);
                $tag = $objectId === null ? null : $this->mirrorOf($objectId);
                if ($tag !== null) {
                    $this->assertOwns($verifiedActor, $tag, (string)$objectId);
                    $entry = $this->entryFromObject($form, $object);
                    $this->entryManager()->update($tag, $this->withoutMarker($entry), false);
                    $this->rememberGeneratedTitle($tag, $entry);
                }
                break;

            case 'Delete':
                $objectId = $this->objectId($activity['object'] ?? null);
                $tag = $objectId === null ? null : $this->mirrorOf($objectId);
                if ($tag !== null) {
                    $this->assertOwns($verifiedActor, $tag, (string)$objectId);
                    $this->entryManager()->delete($tag, true);
                }
                break;
        }
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

            if (!is_array($object) || !is_string($object['id'] ?? null)) {
                continue;
            }

            $remoteObjectIds[] = $object['id'];

            try {
                $this->syncItem(is_string($type) ? $type : null, $object, $actorUri, $form, $stats);
            } catch (\Exception $e) {
                $stats['failed']++;
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
            $entry['read-only'] = 1;
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
        $this->entryManager()->update($tag, $this->withoutMarker($entry), false);
        $this->rememberGeneratedTitle($tag, $entry);
        $stats['updated']++;
    }

    /**
     * Turns a remote object into entry fields, titling it from its summary or text when it has no name, as Mastodon notes do.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $object
     *
     * @return array<string, mixed>
     */
    private function entryFromObject(array $form, array $object): array
    {
        $entry = $this->semanticTransformer->convertFromSemanticData($form, $object);
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
