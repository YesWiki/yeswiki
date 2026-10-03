<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\HttpClient\HttpClient;
use YesWiki\Core\Service\TripleStore;

/**
 * Brings remote ActivityPub activity into the wiki: inbox deliveries and outbox syncs, mirrored as entries.
 */
class ActivityPubInbox
{
    public const REMOTE_ACTOR_URI = 'http://outils-reseaux.org/_vocabulary/remoteActor';

    protected $httpClient;
    protected $activityPubService;
    protected $entryManager;
    protected $httpSignatureService;
    protected $semanticTransformer;
    protected $tripleStore;
    protected $ssrfUrlValidator;

    public function __construct(ActivityPubService $activityPubService, EntryManager $entryManager, HttpSignatureService $httpSignatureService, SemanticTransformer $semanticTransformer, TripleStore $tripleStore, SsrfUrlValidator $ssrfUrlValidator)
    {
        $this->httpClient = HttpClient::create();
        $this->activityPubService = $activityPubService;
        $this->entryManager = $entryManager;
        $this->httpSignatureService = $httpSignatureService;
        $this->semanticTransformer = $semanticTransformer;
        $this->tripleStore = $tripleStore;
        $this->ssrfUrlValidator = $ssrfUrlValidator;
    }

    /**
     * Acts on an activity delivered to the inbox, refusing what the signing actor may not do.
     */
    public function processActivity($activity, $form, string $verifiedActor)
    {
        if (!is_array($activity) || empty($activity['type'])) {
            throw new \Exception('Malformed activity');
        }
        if (($activity['actor'] ?? null) !== $verifiedActor) {
            throw new \Exception('The activity claims an actor the request was not signed for');
        }

        switch ($activity['type']) {
            case 'Accept':
                if ($activity['object']['type'] === 'Follow') {
                    $this->activityPubService->addFollowing($form, $activity['actor']);
                }
                break;

            case 'Follow':
                $this->activityPubService->addFollower($form, $activity['actor']);

                $this->activityPubService->postActivity([
                    'type' => 'Accept',
                    'object' => $activity,
                    'to' => $activity['actor'],
                ], $form);

                break;

            case 'Undo':
                if ($activity['object']['type'] === 'Follow') {
                    $this->activityPubService->removeFollower($form, $activity['actor']);
                }

                break;

            case 'Create':
                $object = $activity['object'];
                $entry = $this->semanticTransformer->convertFromSemanticData($form['bn_id_nature'], $object);
                $entry['read-only'] = 1;
                if (!$this->httpSignatureService->sameHost($verifiedActor, $object['id'] ?? '')) {
                    throw new \Exception('An actor can only bring objects from its own host');
                }
                $created = $this->entryManager->create($form['bn_id_nature'], $entry, false, $object['id']);
                $this->rememberOwner($created['id_fiche'] ?? null, $verifiedActor);
                break;

            case 'Update':
                $object = $activity['object'];
                if ($object['id']) {
                    $triples = $this->tripleStore->getMatching(null, TripleStore::SOURCE_URL_URI, $object['id'], '=', '=', '=');
                    if (!empty($triples)) {
                        $tag = $triples[0]['resource'];
                        $this->assertOwns($verifiedActor, $tag, $object['id']);
                        $entry = $this->semanticTransformer->convertFromSemanticData($form['bn_id_nature'], $object);
                        $this->entryManager->update($tag, $entry, false);
                    }
                }
                break;

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
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0];

        $resolve = $this->ssrfUrlValidator->resolveSafe($actorUri);
        $response = $this->httpClient->request('GET', $actorUri, [
            'headers' => ['Accept' => 'application/activity+json'],
            'max_redirects' => 0,
            'resolve' => $resolve,
        ]);
        $actor = json_decode($response->getContent(), true);

        if (empty($actor['outbox'])) {
            return $stats;
        }

        $remoteItems = $this->fetchAllOutboxItems($actor['outbox']);

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

            if (!$object || !isset($object['id'])) {
                continue;
            }

            $remoteObjectIds[] = $object['id'];

            $existingTriples = $this->tripleStore->getMatching(null, TripleStore::SOURCE_URL_URI, $object['id'], '=', '=', '=');

            if ($type === 'Create') {
                if (empty($existingTriples)) {
                    if (!$this->httpSignatureService->sameHost($actorUri, $object['id'])) {
                        continue;
                    }
                    $entry = $this->semanticTransformer->convertFromSemanticData($form['bn_id_nature'], $object);
                    $entry['read-only'] = 1;
                    $created = $this->entryManager->create($form['bn_id_nature'], $entry, false, $object['id']);
                    $this->rememberOwner($created['id_fiche'] ?? null, $actorUri);
                    $stats['created']++;
                } else {
                    $tag = $existingTriples[0]['resource'];
                    if (!$this->isOwnedBy($actorUri, $tag, $object['id'])) {
                        continue;
                    }
                    $entry = $this->semanticTransformer->convertFromSemanticData($form['bn_id_nature'], $object);
                    $this->entryManager->update($tag, $entry, false);
                    $stats['updated']++;
                }
            } elseif ($type === 'Update' && !empty($existingTriples)) {
                $tag = $existingTriples[0]['resource'];
                if (!$this->isOwnedBy($actorUri, $tag, $object['id'])) {
                    continue;
                }
                $entry = $this->semanticTransformer->convertFromSemanticData($form['bn_id_nature'], $object);
                $this->entryManager->update($tag, $entry, false);
                $stats['updated']++;
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

    private function fetchAllOutboxItems(string $outboxUrl): array
    {
        $items = [];
        $url = $outboxUrl;
        $maxPages = 20;

        for ($page = 0; $url && $page < $maxPages; $page++) {
            $resolve = $this->ssrfUrlValidator->resolveSafe($url);
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['Accept' => 'application/activity+json'],
                'max_redirects' => 0,
                'resolve' => $resolve,
            ]);
            $data = json_decode($response->getContent(), true);
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
