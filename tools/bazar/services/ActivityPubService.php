<?php

namespace YesWiki\Bazar\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use YesWiki\Core\Service\TripleStore;

class ActivityPubService
{
    public static $AS_PREFIX = 'https://www.w3.org/ns/activitystreams#';
    public const REMOTE_ACTOR_URI = 'http://outils-reseaux.org/_vocabulary/remoteActor';

    protected $params;
    protected $httpClient;
    protected $webfingerService;
    protected $container;
    protected $httpSignatureService;
    protected $semanticTransformer;
    protected $tripleStore;
    protected $ssrfUrlValidator;

    public function __construct(ParameterBagInterface $params, WebfingerService $webfingerService, ContainerInterface $container, HttpSignatureService $httpSignatureService, SemanticTransformer $semanticTransformer, TripleStore $tripleStore, SsrfUrlValidator $ssrfUrlValidator)
    {
        $this->params = $params;
        $this->httpClient = HttpClient::create();
        $this->webfingerService = $webfingerService;
        $this->container = $container;
        $this->httpSignatureService = $httpSignatureService;
        $this->semanticTransformer = $semanticTransformer;
        $this->tripleStore = $tripleStore;
        $this->ssrfUrlValidator = $ssrfUrlValidator;
    }

    public function isEnabled($form)
    {
        return isset($form['bn_activitypub_enable']) && $form['bn_activitypub_enable'] === '1';
    }

    public function getFormActorUri($form)
    {
        $parsed = parse_url($this->params->get('base_url'));

        return $parsed['scheme'] . '://' . $parsed['host'] . '/actors/' . $form['bn_id_nature'];
    }

    public function getFormCollectionUri($form, $collectionType)
    {
        $parsed = parse_url($this->params->get('base_url'));

        return $parsed['scheme'] . '://' . $parsed['host'] . '/actors/' . $form['bn_id_nature'] . '/' . $collectionType;
    }

    public function getActor($form)
    {
        $actorUrl = $this->getFormActorUri($form);

        $actor = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actorUrl,
            'type' => 'Application',
            'name' => $form['bn_label_nature'],
            'preferredUsername' => $form['bn_activitypub_username'],
            'inbox' => $actorUrl . '/inbox',
            'outbox' => $actorUrl . '/outbox',
            'followers' => $actorUrl . '/followers',
            'following' => $actorUrl . '/following',
            'publicKey' => [
                'id' => $actorUrl . '#main-key',
                'owner' => $actorUrl,
                'publicKeyPem' => $form['bn_activitypub_public_key'],
            ],
        ];

        return $actor;
    }

    public function getActorInbox(string $actorUri): string
    {
        $resolve = $this->ssrfUrlValidator->resolveSafe($actorUri);

        $response = $this->httpClient->request('GET', $actorUri, [
            'headers' => [
                'Accept' => 'application/ld+json',
            ],
            'max_redirects' => 0,
            'resolve' => $resolve,
        ]);

        $actor = json_decode($response->getContent(), true);

        return $actor['inbox'];
    }

    protected function getRecipients($form, $activity)
    {
        if (\is_array($activity['to'])) {
            $recipients = $activity['to'];
        } else {
            $recipients = [$activity['to']];
        }

        $newRecipients = [];

        foreach ($recipients as $recipient) {
            if ($recipient === 'https://www.w3.org/ns/activitystreams#Public') {
                // Ignore
            } elseif ($recipient === $this->getFormCollectionUri($form, 'followers')) {
                $newRecipients = array_merge($newRecipients, $this->getFollowers($form));
            } else {
                $newRecipients[] = $recipient;
            }
        }

        return $newRecipients;
    }

    public function postActivity($activity, $form)
    {
        $activity['@context'] = 'https://www.w3.org/ns/activitystreams';
        $activity['actor'] = $this->getFormActorUri($form);
        $activity['id'] = $activity['actor'] . '#' . strtolower($activity['type']); // Transient URI, allowed by ActivityPub specs

        $recipientsUris = $this->getRecipients($form, $activity);

        foreach ($recipientsUris as $recipientUri) {
            $inboxUri = $this->getActorInbox($recipientUri);
            $resolve = $this->ssrfUrlValidator->resolveSafe($inboxUri);

            $signatureHeaders = $this->httpSignatureService->generateSignature($activity, $inboxUri, $form);

            $response = $this->httpClient->request('POST', $inboxUri, [
                'body' => json_encode($activity, JSON_UNESCAPED_SLASHES),
                'headers' => $signatureHeaders,
                'max_redirects' => 0,
                'resolve' => $resolve,
            ]);

            $body = $response->getContent(false); // The real error message is on the body
            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \Exception("Failed to send activity to $inboxUri (HTTP $statusCode): $body");
            }
        }
    }

    /**
     * Act on an activity that arrived in the inbox, on behalf of the actor that signed for it.
     *
     * @param string $verifiedActor whom the request was signed by
     *
     * @throws \Exception when the activity claims to come from someone else, or reaches for an
     *                    entry that is not the signer's to touch
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
                    $this->addFollowing($form, $activity['actor']);
                }
                break;

            case 'Follow':
                $this->addFollower($form, $activity['actor']);

                // Send back an Accept activity
                $this->postActivity([
                    'type' => 'Accept',
                    'object' => $activity,
                    'to' => $activity['actor'],
                ], $form);

                break;

            case 'Undo':
                if ($activity['object']['type'] === 'Follow') {
                    $this->removeFollower($form, $activity['actor']);
                }

                break;

            case 'Create':
                $object = $activity['object'];
                $entry = $this->semanticTransformer->convertFromSemanticData($form['bn_id_nature'], $object);
                $entry['read-only'] = 1; // Prevent modification from the local interface, as the source of truth is the remote actor
                if (!$this->httpSignatureService->sameHost($verifiedActor, $object['id'] ?? '')) {
                    throw new \Exception('An actor can only bring objects from its own host');
                }
                $entryManager = $this->container->get(EntryManager::class);
                $created = $entryManager->create($form['bn_id_nature'], $entry, false, $object['id']);
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
                        $entryManager = $this->container->get(EntryManager::class);
                        $entryManager->update($tag, $entry, false);
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
                        $entryManager = $this->container->get(EntryManager::class);
                        $entryManager->delete($tag, true);
                    }
                }
                break;
        }
    }

    /**
     * Write down which actor a mirrored entry answers to, so a later Update or Delete can be
     * checked against it.
     */
    protected function rememberOwner(?string $tag, ?string $actorUri): void
    {
        if (empty($tag) || empty($actorUri)) {
            return;
        }
        $this->tripleStore->create($tag, self::REMOTE_ACTOR_URI, $actorUri, '', '');
    }

    /**
     * An entry mirrored from elsewhere belongs to the actor it came from, and to no one else.
     *
     * Entries mirrored before the owner was recorded fall back to the host their source address
     * is on, which is enough to keep one server from reaching into another's entries.
     *
     * @throws \Exception
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

    public function getFollowers($form)
    {
        $followers = $this->tripleStore->getMatching($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', null, '', '');

        return array_map(fn ($f) => $f['value'], $followers);
    }

    public function getFollowing($form)
    {
        $following = $this->tripleStore->getMatching($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', null, '', '');

        return array_map(fn ($f) => $f['value'], $following);
    }

    public function addFollowing($form, $actorUri)
    {
        $this->tripleStore->create($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    public function addFollower($form, $actorUri)
    {
        $this->tripleStore->create($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    public function removeFollowing($form, $actorUri)
    {
        $this->tripleStore->delete($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    public function removeFollower($form, $actorUri)
    {
        $this->tripleStore->delete($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    public function notifyFollowers($form, $entry, $activityType)
    {
        $object = $this->semanticTransformer->convertToSemanticData($form, $entry);
        unset($object['@context']);

        $this->postActivity([
            'type' => $activityType,
            'object' => $object,
            'to' => $this->getFormCollectionUri($form, 'followers'),
        ], $form);
    }

    public function syncActorPosts(string $actorUri, array $form): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        $entryManager = $this->container->get(EntryManager::class);

        // Fetch actor profile to get its outbox URL
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

        // Collect all remote object IDs so we can detect deletions afterwards
        $remoteObjectIds = [];

        foreach ($remoteItems as $item) {
            $type = $item['type'] ?? null;
            $object = $item['object'] ?? null;

            if ($type === 'Delete') {
                // object may be a string (the ID itself) or an array with a Tombstone
                $objectId = is_array($object) ? ($object['id'] ?? null) : $object;
                if ($objectId) {
                    $existingTriples = $this->tripleStore->getMatching(null, TripleStore::SOURCE_URL_URI, $objectId, '=', '=', '=');
                    if (!empty($existingTriples)) {
                        $entryManager->delete($existingTriples[0]['resource'], true);
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
                    $entry = $this->semanticTransformer->convertFromSemanticData($form['bn_id_nature'], $object);
                    $entry['read-only'] = 1;
                    // var_dump($entry);
                    // var_dump($object);
                    // exit();
                    $entryManager->create($form['bn_id_nature'], $entry, false, $object['id']);
                    $stats['created']++;
                } else {
                    $tag = $existingTriples[0]['resource'];
                    $entry = $this->semanticTransformer->convertFromSemanticData($form['bn_id_nature'], $object);
                    $entryManager->update($tag, $entry, false);
                    $stats['updated']++;
                }
            } elseif ($type === 'Update' && !empty($existingTriples)) {
                $tag = $existingTriples[0]['resource'];
                $entry = $this->semanticTransformer->convertFromSemanticData($form['bn_id_nature'], $object);
                $entryManager->update($tag, $entry, false);
                $stats['updated']++;
            }
        }

        // Detect objects deleted on the remote side (no longer in the outbox):
        // any local entry whose sourceUrl comes from the same host as the actor
        // but is absent from the current outbox should be removed.
        $actorHost = parse_url($actorUri, PHP_URL_HOST);
        $localEntries = $entryManager->search(['idtypeannonce' => $form['bn_id_nature']]);

        foreach ($localEntries as $entry) {
            $tag = $entry['id_fiche'];
            $sourceTriples = $this->tripleStore->getMatching($tag, TripleStore::SOURCE_URL_URI, null, '=', '=', '');

            if (!empty($sourceTriples)) {
                $sourceUrl = $sourceTriples[0]['value'];
                if (parse_url($sourceUrl, PHP_URL_HOST) === $actorHost && !in_array($sourceUrl, $remoteObjectIds)) {
                    $entryManager->delete($tag, true);
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
        $maxPages = 20; // Safety limit to avoid infinite loops

        for ($page = 0; $url && $page < $maxPages; $page++) {
            $resolve = $this->ssrfUrlValidator->resolveSafe($url);
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['Accept' => 'application/activity+json'],
                'max_redirects' => 0,
                'resolve' => $resolve,
            ]);
            $data = json_decode($response->getContent(), true);
            $type = $data['type'] ?? null;

            // Paginated collection: follow `first` before collecting items
            if ($type === 'OrderedCollection' && isset($data['first']) && !isset($data['orderedItems'])) {
                $url = is_string($data['first']) ? $data['first'] : ($data['first']['id'] ?? null);
                continue;
            }

            if (isset($data['orderedItems'])) {
                $items = array_merge($items, $data['orderedItems']);
            } elseif (isset($data['items'])) {
                $items = array_merge($items, $data['items']);
            }

            // Follow the next page, if any
            if (isset($data['next'])) {
                $url = is_string($data['next']) ? $data['next'] : ($data['next']['id'] ?? null);
            } else {
                break;
            }
        }

        return $items;
    }
}
