<?php

namespace YesWiki\Federation\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Kernel\Service\SsrfUrlValidator;
use YesWiki\Kernel\Service\TripleStore;

class ActivityPubService
{
    public static string $AS_PREFIX = 'https://www.w3.org/ns/activitystreams#';

    protected ParameterBagInterface $params;
    protected HttpClientInterface $httpClient;
    protected WebfingerService $webfingerService;
    protected ContainerInterface $container;
    protected HttpSignatureService $httpSignatureService;
    protected SemanticTransformer $semanticTransformer;
    protected TripleStore $tripleStore;
    protected SsrfUrlValidator $ssrfUrlValidator;

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

    /**
     * @param array<string, mixed> $form
     */
    public function isEnabled(array $form): bool
    {
        return isset($form['activitypub_enable']) && $form['activitypub_enable'] === '1';
    }

    /**
     * @param array<string, mixed> $form
     */
    public function getFormActorUri(array $form): string
    {
        // parse_url() returns false for a malformed URL, and the keys are all optional even
        // when it succeeds -- a wiki whose base_url is not a full URL would have fataled here
        // rather than produced a wrong actor URI (ticket 40)
        $baseUrl = $this->params->get('base_url');
        $parsed = parse_url(is_string($baseUrl) ? $baseUrl : '');
        $scheme = is_array($parsed) ? ($parsed['scheme'] ?? 'https') : 'https';
        $host = is_array($parsed) ? ($parsed['host'] ?? '') : '';

        return $scheme . '://' . $host . '/actors/' . $form['id'];
    }

    /**
     * @param array<string, mixed> $form
     */
    public function getFormCollectionUri(array $form, string $collectionType): string
    {
        return $this->getFormActorUri($form) . '/' . $collectionType;
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    public function getActor(array $form): array
    {
        $actorUrl = $this->getFormActorUri($form);

        $actor = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actorUrl,
            'type' => 'Application',
            'name' => $form['label'],
            'preferredUsername' => $form['activitypub_username'],
            'inbox' => $actorUrl . '/inbox',
            'outbox' => $actorUrl . '/outbox',
            'followers' => $actorUrl . '/followers',
            'following' => $actorUrl . '/following',
            'publicKey' => [
                'id' => $actorUrl . '#main-key',
                'owner' => $actorUrl,
                'publicKeyPem' => $form['activitypub_public_key'],
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

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $activity
     *
     * @return list<string>
     */
    protected function getRecipients(array $form, array $activity): array
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

    /**
     * @param array<string, mixed> $activity
     * @param array<string, mixed> $form
     */
    public function postActivity(array $activity, array $form): void
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
     * @param array<string, mixed> $activity
     * @param array<string, mixed> $form
     */
    public function processActivity(array $activity, array $form): void
    {
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
                $entry = $this->semanticTransformer->convertFromSemanticData($form['id'], $object);
                $entry['read-only'] = 1; // Prevent modification from the local interface, as the source of truth is the remote actor
                $entryManager = $this->container->get(EntryManager::class);
                $entryManager->create($form['id'], $entry, false, $object['id']);
                break;

            case 'Update':
                $object = $activity['object'];
                if ($object['id']) {
                    $triples = $this->tripleStore->getMatching(null, TripleStore::SOURCE_URL_URI, $object['id'], '=', '=', '=');
                    if (!empty($triples)) {
                        $tag = $triples[0]['resource'];
                        $entry = $this->semanticTransformer->convertFromSemanticData($form['id'], $object);
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
                        $entryManager = $this->container->get(EntryManager::class);
                        $entryManager->delete($tag, true);
                    }
                }
                break;
        }
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return list<string>
     */
    public function getFollowers(array $form): array
    {
        $followers = $this->tripleStore->getMatching($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', null, '', '');

        return array_map(fn ($f) => is_string($f['value']) ? $f['value'] : '', $followers);
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return list<string>
     */
    public function getFollowing(array $form): array
    {
        $following = $this->tripleStore->getMatching($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', null, '', '');

        return array_map(fn ($f) => is_string($f['value']) ? $f['value'] : '', $following);
    }

    /**
     * @param array<string, mixed> $form
     */
    public function addFollowing(array $form, string $actorUri): void
    {
        $this->tripleStore->create($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    /**
     * @param array<string, mixed> $form
     */
    public function addFollower(array $form, string $actorUri): void
    {
        $this->tripleStore->create($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    /**
     * @param array<string, mixed> $form
     */
    public function removeFollowing(array $form, string $actorUri): void
    {
        $this->tripleStore->delete($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    /**
     * @param array<string, mixed> $form
     */
    public function removeFollower(array $form, string $actorUri): void
    {
        $this->tripleStore->delete($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     */
    public function notifyFollowers(array $form, array $entry, string $activityType): void
    {
        $object = $this->semanticTransformer->convertToSemanticData($form, $entry);
        unset($object['@context']);

        $this->postActivity([
            'type' => $activityType,
            'object' => $object,
            'to' => $this->getFormCollectionUri($form, 'followers'),
        ], $form);
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array{created: int, updated: int, deleted: int}
     */
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
                    $entry = $this->semanticTransformer->convertFromSemanticData($form['id'], $object);
                    $entry['read-only'] = 1;
                    // var_dump($entry);
                    // var_dump($object);
                    // exit();
                    $entryManager->create($form['id'], $entry, false, $object['id']);
                    $stats['created']++;
                } else {
                    $tag = $existingTriples[0]['resource'];
                    $entry = $this->semanticTransformer->convertFromSemanticData($form['id'], $object);
                    $entryManager->update($tag, $entry, false);
                    $stats['updated']++;
                }
            } elseif ($type === 'Update' && !empty($existingTriples)) {
                $tag = $existingTriples[0]['resource'];
                $entry = $this->semanticTransformer->convertFromSemanticData($form['id'], $object);
                $entryManager->update($tag, $entry, false);
                $stats['updated']++;
            }
        }

        // Detect objects deleted on the remote side (no longer in the outbox):
        // any local entry whose sourceUrl comes from the same host as the actor
        // but is absent from the current outbox should be removed.
        $actorHost = parse_url($actorUri, PHP_URL_HOST);
        $localEntries = $entryManager->search(['id' => $form['id']]);

        foreach ($localEntries as $entry) {
            $tag = $entry['tag'];
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

    /**
     * @return array<mixed>
     */
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
