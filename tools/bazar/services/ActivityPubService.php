<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use YesWiki\Core\Service\AfterResponse;
use YesWiki\Core\Service\TripleStore;

class ActivityPubService
{
    public const PUBLIC = 'https://www.w3.org/ns/activitystreams#Public';

    public static $AS_PREFIX = 'https://www.w3.org/ns/activitystreams#';

    protected $params;
    protected $httpClient;
    protected $webfingerService;
    protected $httpSignatureService;
    protected $semanticTransformer;
    protected $tripleStore;
    protected $ssrfUrlValidator;
    protected $keyStore;
    protected $profiles;
    protected $log;
    protected $afterResponse;

    public function __construct(ParameterBagInterface $params, WebfingerService $webfingerService, HttpSignatureService $httpSignatureService, SemanticTransformer $semanticTransformer, TripleStore $tripleStore, SsrfUrlValidator $ssrfUrlValidator, ActivityPubKeyStore $keyStore, ActivityPubProfiles $profiles, ActivityPubLog $log, ?AfterResponse $afterResponse = null)
    {
        $this->afterResponse = $afterResponse ?? new AfterResponse();
        $this->log = $log;
        $this->keyStore = $keyStore;
        $this->profiles = $profiles;
        $this->params = $params;
        $this->httpClient = HttpClient::create();
        $this->webfingerService = $webfingerService;
        $this->httpSignatureService = $httpSignatureService;
        $this->semanticTransformer = $semanticTransformer;
        $this->tripleStore = $tripleStore;
        $this->ssrfUrlValidator = $ssrfUrlValidator;
    }

    public function isEnabled($form)
    {
        return isset($form['bn_activitypub_enable']) && $form['bn_activitypub_enable'] === '1';
    }

    /**
     * Whether the form is published as an actor: ActivityPub enabled and a username set in its profile.
     */
    public function hasActor($form): bool
    {
        return $this->isEnabled($form) && $this->username($form) !== '';
    }

    public function username($form): string
    {
        return $this->profiles->username($form['bn_id_nature'] ?? '');
    }

    /**
     * The form's handle as people type it on other servers, like @agenda@example.org.
     */
    public function handle($form): string
    {
        return '@' . $this->username($form) . '@' . parse_url($this->getFormActorUri($form), PHP_URL_HOST);
    }

    public function getFormActorUri($form)
    {
        $parsed = parse_url($this->params->get('base_url'));

        return $parsed['scheme'] . '://' . $parsed['host'] . '/actors/' . $form['bn_id_nature'];
    }

    /**
     * What an admin's browser fetches to check that the web server passes WebFinger and the actor on to the wiki.
     *
     * @return array{atDomainRoot:bool,webfinger:string,actor:string}
     */
    public function routesToCheck($form): array
    {
        $parsed = parse_url($this->params->get('base_url'));

        return [
            'atDomainRoot' => isDomainRootUrl($this->params->get('base_url')),
            'webfinger' => $parsed['scheme'] . '://' . $parsed['host'] . '/.well-known/webfinger?resource=' . rawurlencode('acct:' . $this->username($form) . '@' . $parsed['host']),
            'actor' => $this->getFormActorUri($form),
        ];
    }

    public function getFormCollectionUri($form, $collectionType)
    {
        $parsed = parse_url($this->params->get('base_url'));

        return $parsed['scheme'] . '://' . $parsed['host'] . '/actors/' . $form['bn_id_nature'] . '/' . $collectionType;
    }

    /**
     * The key a form signs its requests with, or null when it has no ActivityPub username.
     *
     * @return array{keyId:string,privateKey:string}|null
     */
    public function signer($form): ?array
    {
        if ($this->username($form) === '') {
            return null;
        }

        return [
            'keyId' => $this->getFormActorUri($form) . '#main-key',
            'privateKey' => $this->keyStore->privateKey((string)$form['bn_id_nature']),
        ];
    }

    /**
     * Fetches a remote ActivityPub document with a request signed by the form, as GoToSocial requires.
     */
    public function fetch(string $uri, $form): array
    {
        return $this->httpSignatureService->fetch($uri, $this->signer($form));
    }

    public function profile($form): array
    {
        return $this->profiles->get($form['bn_id_nature'] ?? '');
    }

    public function getActor($form)
    {
        $actorUrl = $this->getFormActorUri($form);
        $profile = $this->profile($form);

        $actor = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
                [
                    'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
                    'toot' => 'http://joinmastodon.org/ns#',
                    'discoverable' => 'toot:discoverable',
                    'indexable' => 'toot:indexable',
                    'schema' => 'http://schema.org#',
                    'PropertyValue' => 'schema:PropertyValue',
                    'value' => 'schema:value',
                ],
            ],
            'id' => $actorUrl,
            'type' => 'Application',
            'name' => $form['bn_label_nature'],
            'preferredUsername' => $profile['username'],
            'summary' => $this->toHtml($profile['summary']) ?: $this->toHtml($form['bn_description'] ?? ''),
            'url' => $this->wikiRootUrl(),
            'manuallyApprovesFollowers' => $profile['approve_followers'],
            'discoverable' => true,
            'indexable' => true,
            'inbox' => $actorUrl . '/inbox',
            'outbox' => $actorUrl . '/outbox',
            'followers' => $actorUrl . '/followers',
            'following' => $actorUrl . '/following',
            'publicKey' => [
                'id' => $actorUrl . '#main-key',
                'owner' => $actorUrl,
                'publicKeyPem' => $this->keyStore->publicKey((string)$form['bn_id_nature']),
            ],
        ];

        foreach (['icon', 'image'] as $property) {
            $image = $this->image($profile[$property]);
            if ($image !== null) {
                $actor[$property] = $image;
            }
        }

        $fields = $this->profileFields($profile['fields']);
        if (!empty($fields)) {
            $actor['attachment'] = $fields;
        }

        return $actor;
    }

    /**
     * The wiki's address without its query separator, used as the profile link.
     */
    protected function wikiRootUrl(): string
    {
        return rtrim((string)$this->params->get('base_url'), '?');
    }

    /**
     * Plain text turned into the HTML paragraphs ActivityPub profiles expect, links included.
     */
    protected function toHtml(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if ($text !== strip_tags($text)) {
            return $text;
        }

        $paragraphs = preg_split('/\R{2,}/', $text);

        return implode('', array_map(
            fn ($paragraph) => '<p>' . nl2br($this->linkify($paragraph), false) . '</p>',
            $paragraphs
        ));
    }

    protected function linkify(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES);

        return preg_replace_callback(
            '~https?://[^\s<]+~',
            fn ($m) => '<a href="' . $m[0] . '" rel="me nofollow noopener" target="_blank">' . $m[0] . '</a>',
            $escaped
        );
    }

    /**
     * An Image object for a URL or a path relative to the wiki, or null when none is set.
     */
    protected function image(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = preg_replace('~/[^/]*$~', '/', $this->wikiRootUrl()) . ltrim($url, '/');
        }

        $image = ['type' => 'Image', 'url' => $url];
        $mediaTypes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'svg' => 'image/svg+xml'];
        $extension = strtolower(pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (isset($mediaTypes[$extension])) {
            $image['mediaType'] = $mediaTypes[$extension];
        }

        return $image;
    }

    /**
     * Profile fields as PropertyValues, from a label => value map.
     */
    protected function profileFields($fields): array
    {
        $propertyValues = [];
        foreach (is_array($fields) ? $fields : [] as $name => $value) {
            if (trim((string)$name) === '' || trim((string)$value) === '') {
                continue;
            }
            $propertyValues[] = [
                'type' => 'PropertyValue',
                'name' => trim((string)$name),
                'value' => $this->linkify(trim((string)$value)),
            ];
        }

        return $propertyValues;
    }

    public function getActorInbox(string $actorUri, $form): string
    {
        $actor = $this->fetch($actorUri, $form);
        if (empty($actor['inbox']) || !is_string($actor['inbox'])) {
            throw new \Exception("{$actorUri} has no inbox");
        }

        return $actor['inbox'];
    }

    protected function getRecipients($form, $activity)
    {
        $recipients = [];
        foreach (['to', 'cc'] as $field) {
            $recipients = array_merge($recipients, (array)($activity[$field] ?? []));
        }

        $newRecipients = [];

        foreach ($recipients as $recipient) {
            if ($recipient === self::PUBLIC) {
                continue;
            }
            if ($recipient === $this->getFormCollectionUri($form, 'followers')) {
                $newRecipients = array_merge($newRecipients, $this->getFollowers($form));
            } else {
                $newRecipients[] = $recipient;
            }
        }

        return array_values(array_unique($newRecipients));
    }

    /**
     * Delivers an activity to each recipient's inbox, and returns it with its id; one failing inbox does not stop the others.
     */
    public function postActivity($activity, $form)
    {
        $activity = ['@context' => $activity['@context'] ?? 'https://www.w3.org/ns/activitystreams'] + $activity;
        $activity['actor'] = $this->getFormActorUri($form);
        $activity['id'] ??= $this->newActivityId($form);

        $body = json_encode($activity, JSON_UNESCAPED_SLASHES);
        $signer = $this->signer($form);
        if ($signer === null) {
            throw new \Exception('This form has no ActivityPub username');
        }
        $failures = [];

        foreach ($this->getRecipients($form, $activity) as $recipientUri) {
            $inboxUri = null;
            $statusCode = null;
            $responseBody = null;
            try {
                $inboxUri = $this->getActorInbox($recipientUri, $form);

                $response = $this->httpClient->request('POST', $inboxUri, [
                    'body' => $body,
                    'headers' => $this->httpSignatureService->signHeaders('POST', $inboxUri, $signer, $body),
                    'max_redirects' => 0,
                    'timeout' => HttpSignatureService::TIMEOUT,
                    'max_duration' => HttpSignatureService::MAX_DURATION,
                    'resolve' => $this->ssrfUrlValidator->resolveSafe($inboxUri),
                ]);

                $statusCode = $response->getStatusCode();
                $responseBody = $response->getContent(false);
                if ($statusCode < 200 || $statusCode >= 300) {
                    throw new \Exception("HTTP $statusCode: " . $responseBody);
                }
                $this->log->delivered($form['bn_id_nature'] ?? '', $recipientUri, $inboxUri, $activity, $statusCode, null, $responseBody);
            } catch (\Throwable $e) {
                $failures[] = "$recipientUri: " . $e->getMessage();
                $this->log->delivered($form['bn_id_nature'] ?? '', $recipientUri, $inboxUri, $activity, $statusCode, $e->getMessage(), $responseBody);
            }
        }

        if (!empty($failures)) {
            throw new \Exception('Failed to deliver ' . $activity['type'] . ' to ' . implode(' ; ', $failures));
        }

        return $activity;
    }

    protected function newActivityId($form): string
    {
        return $this->getFormActorUri($form) . '/activities/' . bin2hex(random_bytes(16));
    }

    /**
     * Asks a remote actor to be followed, remembering the Follow until it is accepted or undone.
     */
    public function follow($form, string $actorUri): void
    {
        $this->forgetFollowRequest($form, $actorUri);
        $activity = $this->postActivity(['type' => 'Follow', 'object' => $actorUri, 'to' => $actorUri], $form);
        $this->tripleStore->create(
            $this->getFormCollectionUri($form, 'following'),
            self::$AS_PREFIX . 'Follow',
            json_encode(['actor' => $actorUri, 'id' => $activity['id']], JSON_UNESCAPED_SLASHES),
            '',
            ''
        );
    }

    /**
     * Stops following a remote actor, undoing the Follow it was sent.
     */
    public function unfollow($form, string $actorUri): void
    {
        $this->removeFollowing($form, $actorUri);
        $followId = $this->followRequestId($form, $actorUri) ?? $this->getFormActorUri($form) . '#follow';
        $this->forgetFollowRequest($form, $actorUri);

        $this->postActivity([
            'type' => 'Undo',
            'object' => [
                'id' => $followId,
                'type' => 'Follow',
                'actor' => $this->getFormActorUri($form),
                'object' => $actorUri,
            ],
            'to' => $actorUri,
        ], $form);
    }

    /**
     * Records an accepted Follow, when the form did ask that actor; returns whether it did.
     */
    public function acceptFollow($form, string $actorUri, ?string $followId): bool
    {
        $pendingId = $this->followRequestId($form, $actorUri);
        if ($pendingId === null || ($followId !== null && $followId !== $pendingId)) {
            return false;
        }
        $this->addFollowing($form, $actorUri);

        return true;
    }

    public function rejectFollow($form, string $actorUri): void
    {
        $this->forgetFollowRequest($form, $actorUri);
        $this->removeFollowing($form, $actorUri);
    }

    /**
     * The actors the form asked to follow that have not accepted yet.
     */
    public function getPendingFollowing($form): array
    {
        return array_values(array_diff(
            array_map(fn ($request) => $request['actor'], $this->followRequests($form)),
            $this->getFollowing($form)
        ));
    }

    protected function followRequestId($form, string $actorUri): ?string
    {
        foreach ($this->followRequests($form) as $request) {
            if ($request['actor'] === $actorUri) {
                return $request['id'];
            }
        }

        return null;
    }

    protected function forgetFollowRequest($form, string $actorUri): void
    {
        foreach ($this->followRequests($form) as $raw => $request) {
            if ($request['actor'] === $actorUri) {
                $this->tripleStore->delete($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'Follow', $raw, '', '');
            }
        }
    }

    /**
     * The Follow activities the form sent, keyed by their stored value.
     *
     * @return array<string,array{actor:string,id:string}>
     */
    protected function followRequests($form): array
    {
        $requests = [];
        $triples = $this->tripleStore->getMatching($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'Follow', null, '', '');
        foreach ($triples as $triple) {
            $request = json_decode($triple['value'], true);
            if (isset($request['actor'], $request['id'])) {
                $requests[$triple['value']] = $request;
            }
        }

        return $requests;
    }

    /**
     * Takes in a Follow sent to the form: accepted right away unless the profile asks to approve followers, and kept as a request while it is not.
     */
    public function receiveFollow($form, array $follow): void
    {
        $actorUri = (string)($follow['actor'] ?? '');
        $followId = is_string($follow['id'] ?? null) ? $follow['id'] : null;
        if ($actorUri === '' || $followId === null) {
            throw new \Exception('A Follow needs an actor and an id');
        }
        $this->forgetIncomingRequest($form, $actorUri);
        $this->tripleStore->create(
            $this->getFormCollectionUri($form, 'followers'),
            self::$AS_PREFIX . 'Follow',
            json_encode(['actor' => $actorUri, 'id' => $followId], JSON_UNESCAPED_SLASHES),
            '',
            ''
        );

        if (!$this->profile($form)['approve_followers'] || in_array($actorUri, $this->getFollowers($form), true)) {
            try {
                $this->acceptFollower($form, $actorUri);
            } catch (\Exception $e) {
                error_log('ActivityPub: ' . $e->getMessage());
            }
        }
    }

    /**
     * Accepts a pending Follow request; it stays pending when the Accept cannot be delivered.
     */
    public function acceptFollower($form, string $actorUri): void
    {
        $followId = $this->incomingRequestId($form, $actorUri);
        if ($followId === null) {
            throw new \Exception("No pending follow request from {$actorUri}");
        }
        $this->postActivity(['type' => 'Accept', 'object' => $this->incomingFollow($form, $actorUri, $followId), 'to' => $actorUri], $form);
        if (!in_array($actorUri, $this->getFollowers($form), true)) {
            $this->addFollower($form, $actorUri);
        }
        $this->forgetIncomingRequest($form, $actorUri);
    }

    /**
     * Refuses a pending Follow request, or removes a follower, and tells the remote actor.
     */
    public function rejectFollower($form, string $actorUri): void
    {
        $followId = $this->incomingRequestId($form, $actorUri) ?? $actorUri . '#follow';
        $this->forgetIncomingRequest($form, $actorUri);
        $this->removeFollower($form, $actorUri);
        $this->postActivity(['type' => 'Reject', 'object' => $this->incomingFollow($form, $actorUri, $followId), 'to' => $actorUri], $form);
    }

    /**
     * Forgets everything an actor asked: its pending request and its place among the followers.
     */
    public function undoFollow($form, string $actorUri): void
    {
        $this->forgetIncomingRequest($form, $actorUri);
        $this->removeFollower($form, $actorUri);
    }

    /**
     * The actors waiting for the form to accept their Follow.
     */
    public function getFollowRequests($form): array
    {
        return array_values(array_diff(
            array_map(fn ($request) => $request['actor'], $this->incomingRequests($form)),
            $this->getFollowers($form)
        ));
    }

    protected function incomingFollow($form, string $actorUri, string $followId): array
    {
        return ['id' => $followId, 'type' => 'Follow', 'actor' => $actorUri, 'object' => $this->getFormActorUri($form)];
    }

    protected function incomingRequestId($form, string $actorUri): ?string
    {
        foreach ($this->incomingRequests($form) as $request) {
            if ($request['actor'] === $actorUri) {
                return $request['id'];
            }
        }

        return null;
    }

    protected function forgetIncomingRequest($form, string $actorUri): void
    {
        foreach ($this->incomingRequests($form) as $raw => $request) {
            if ($request['actor'] === $actorUri) {
                $this->tripleStore->delete($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'Follow', $raw, '', '');
            }
        }
    }

    /**
     * The Follow activities received by the form, keyed by their stored value.
     *
     * @return array<string,array{actor:string,id:string}>
     */
    protected function incomingRequests($form): array
    {
        $requests = [];
        $triples = $this->tripleStore->getMatching($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'Follow', null, '', '');
        foreach ($triples as $triple) {
            $request = json_decode($triple['value'], true);
            if (isset($request['actor'], $request['id'])) {
                $requests[$triple['value']] = $request;
            }
        }

        return $requests;
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

    /**
     * The ActivityPub object an entry is published as, addressed to the public and the form's followers.
     */
    public function objectFor($form, $entry): array
    {
        $object = $this->semanticTransformer->convertToSemanticData($form, $entry);
        unset($object['@context']);
        $object['attributedTo'] ??= $this->getFormActorUri($form);
        $object['to'] ??= [self::PUBLIC];
        $object['cc'] ??= [$this->getFormCollectionUri($form, 'followers')];

        return $object;
    }

    /**
     * Sends the followers the actor as it now is, so remote servers refresh their cached profile; a follower that cannot be reached is logged, not fatal.
     */
    public function announceProfile($form): void
    {
        if (!$this->hasActor($form)) {
            return;
        }
        $actor = $this->getActor($form);
        $context = $actor['@context'];
        unset($actor['@context']);

        $this->deliverToFollowers([
            '@context' => $context,
            'type' => 'Update',
            'object' => $actor,
            'to' => [self::PUBLIC],
            'cc' => [$this->getFormCollectionUri($form, 'followers')],
        ], $form);
    }

    /**
     * Tells the followers an entry was created, updated or deleted; a follower that cannot be reached is logged, not fatal.
     */
    public function notifyFollowers($form, $entry, $activityType)
    {
        $object = $this->objectFor($form, $entry);
        if ($activityType === 'Delete') {
            $object = ['id' => $object['id'], 'type' => 'Tombstone', 'formerType' => $object['type'] ?? 'Object'];
        }

        $this->deliverToFollowers([
            'type' => $activityType,
            'object' => $object,
            'to' => [self::PUBLIC],
            'cc' => [$this->getFormCollectionUri($form, 'followers')],
        ], $form);
    }

    /**
     * Sends an activity to the followers once the response has gone, so a slow follower never holds up the person saving.
     */
    protected function deliverToFollowers(array $activity, $form): void
    {
        $this->afterResponse->defer(function () use ($activity, $form) {
            try {
                $this->postActivity($activity, $form);
            } catch (\Exception $e) {
                error_log('ActivityPub: ' . $e->getMessage());
            }
        });
    }
}
