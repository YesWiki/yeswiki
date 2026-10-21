<?php

namespace YesWiki\Federation\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Kernel\Service\SsrfUrlValidator;
use YesWiki\Kernel\Service\TripleStore;

/** Each form as an ActivityPub actor: its profile, its key, who it follows and who follows it. */
class ActivityPubService
{
    public const PUBLIC = 'https://www.w3.org/ns/activitystreams#Public';
    public static string $AS_PREFIX = 'https://www.w3.org/ns/activitystreams#';

    protected HttpClientInterface $httpClient;

    public function __construct(
        protected ParameterBagInterface $params,
        protected HttpSignatureService $httpSignatureService,
        protected SemanticTransformer $semanticTransformer,
        protected TripleStore $tripleStore,
        protected SsrfUrlValidator $ssrfUrlValidator,
        protected ActivityPubKeyStore $keyStore,
        protected ActivityPubProfiles $profiles,
        protected ActivityPubLog $log,
    ) {
        $this->httpClient = HttpClient::create();
    }

    /** @param array<string, mixed> $form */
    public function isEnabled(array $form): bool
    {
        return ($form['activitypub_enable'] ?? null) === '1';
    }

    /**
     * Whether the form is published as an actor: ActivityPub enabled and a username set in its profile.
     *
     * @param array<string, mixed> $form
     */
    public function hasActor(array $form): bool
    {
        return $this->isEnabled($form) && $this->username($form) !== '';
    }

    /** @param array<string, mixed> $form */
    public function username(array $form): string
    {
        return $this->profile($form)['username'];
    }

    /**
     * The form's handle as people type it on other servers, like @agenda@example.org.
     *
     * @param array<string, mixed> $form
     */
    public function handle(array $form): string
    {
        return '@' . $this->username($form) . '@' . parse_url($this->getFormActorUri($form), PHP_URL_HOST);
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array{username: string, summary: string, icon: string, image: string, fields: array<string, string>, approve_followers: bool, accept_unfollowed: bool, accept_non_public: bool, accept_replies: bool}
     */
    public function profile(array $form): array
    {
        return $this->profiles->get($form);
    }

    /** @param array<string, mixed> $form */
    public function getFormActorUri(array $form): string
    {
        $baseUrl = $this->params->get('base_url');
        $parsed = parse_url(is_string($baseUrl) ? $baseUrl : '');
        $scheme = is_array($parsed) ? ($parsed['scheme'] ?? 'https') : 'https';
        $host = is_array($parsed) ? ($parsed['host'] ?? '') : '';

        return $scheme . '://' . $host . '/actors/' . $form['id'];
    }

    /** @param array<string, mixed> $form */
    public function getFormCollectionUri(array $form, string $collectionType): string
    {
        return $this->getFormActorUri($form) . '/' . $collectionType;
    }

    /**
     * The key a form signs its requests with, or null when it has no ActivityPub username.
     *
     * @param array<string, mixed> $form
     *
     * @return array{keyId: string, privateKey: string}|null
     */
    public function signer(array $form): ?array
    {
        if ($this->username($form) === '') {
            return null;
        }

        return [
            'keyId' => $this->getFormActorUri($form) . '#main-key',
            'privateKey' => $this->keyStore->privateKey((string)$form['id']),
        ];
    }

    /**
     * Fetches a remote ActivityPub document with a request signed by the form, as GoToSocial requires.
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    public function fetch(string $uri, array $form): array
    {
        return $this->httpSignatureService->fetch($uri, $this->signer($form));
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    public function getActor(array $form): array
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
            'name' => $form['label'] ?? '',
            'preferredUsername' => $profile['username'],
            'summary' => $this->toHtml($profile['summary']) ?: $this->toHtml(is_string($form['description'] ?? null) ? $form['description'] : ''),
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
                'publicKeyPem' => $this->keyStore->publicKey((string)$form['id']),
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

    /** The wiki's address without its query separator, used as the profile link. */
    protected function wikiRootUrl(): string
    {
        $baseUrl = $this->params->get('base_url');

        return rtrim(is_string($baseUrl) ? $baseUrl : '', '?');
    }

    /** Plain text turned into the HTML paragraphs ActivityPub profiles expect, links included. */
    protected function toHtml(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if ($text !== strip_tags($text)) {
            return $text;
        }

        $paragraphs = preg_split('/\R{2,}/', $text) ?: [$text];

        return implode('', array_map(
            fn ($paragraph) => '<p>' . nl2br($this->linkify($paragraph), false) . '</p>',
            $paragraphs
        ));
    }

    protected function linkify(string $text): string
    {
        return (string)preg_replace_callback(
            '~https?://[^\s<]+~',
            fn ($m) => '<a href="' . $m[0] . '" rel="me nofollow noopener" target="_blank">' . $m[0] . '</a>',
            htmlspecialchars($text, ENT_QUOTES)
        );
    }

    /**
     * An Image object for a URL or a path relative to the wiki, or null when none is set.
     *
     * @return array{type: string, url: string, mediaType?: string}|null
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
     *
     * @param array<string, string> $fields
     *
     * @return list<array{type: string, name: string, value: string}>
     */
    protected function profileFields(array $fields): array
    {
        $propertyValues = [];
        foreach ($fields as $name => $value) {
            if (trim((string)$name) === '' || trim($value) === '') {
                continue;
            }
            $propertyValues[] = [
                'type' => 'PropertyValue',
                'name' => trim((string)$name),
                'value' => $this->linkify(trim($value)),
            ];
        }

        return $propertyValues;
    }

    /** @param array<string, mixed> $form */
    public function getActorInbox(string $actorUri, array $form): string
    {
        $actor = $this->fetch($actorUri, $form);
        if (empty($actor['inbox']) || !is_string($actor['inbox'])) {
            throw new \Exception("{$actorUri} has no inbox");
        }

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
        $recipients = [];
        foreach (['to', 'cc'] as $field) {
            $recipients = array_merge($recipients, (array)($activity[$field] ?? []));
        }

        $newRecipients = [];
        foreach ($recipients as $recipient) {
            if (!is_string($recipient) || $recipient === self::PUBLIC) {
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
     *
     * @param array<string, mixed> $activity
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    public function postActivity(array $activity, array $form): array
    {
        $activity = ['@context' => $activity['@context'] ?? 'https://www.w3.org/ns/activitystreams'] + $activity;
        $activity['actor'] = $this->getFormActorUri($form);
        $activity['id'] ??= $this->newActivityId($form);

        $body = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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
                    'resolve' => $this->ssrfUrlValidator->resolveSafe($inboxUri),
                ]);

                $statusCode = $response->getStatusCode();
                $responseBody = $response->getContent(false);
                if ($statusCode < 200 || $statusCode >= 300) {
                    throw new \Exception("HTTP $statusCode: " . $responseBody);
                }
                $this->log->delivered((string)$form['id'], $recipientUri, $inboxUri, $activity, $statusCode, null, $responseBody);
            } catch (\Throwable $e) {
                $failures[] = "$recipientUri: " . $e->getMessage();
                $this->log->delivered((string)$form['id'], $recipientUri, $inboxUri, $activity, $statusCode, $e->getMessage(), $responseBody);
            }
        }

        if (!empty($failures)) {
            throw new \Exception('Failed to deliver ' . $activity['type'] . ' to ' . implode(' ; ', $failures));
        }

        return $activity;
    }

    /** @param array<string, mixed> $form */
    protected function newActivityId(array $form): string
    {
        return $this->getFormActorUri($form) . '/activities/' . bin2hex(random_bytes(16));
    }

    /**
     * Asks a remote actor to be followed, remembering the Follow until it is accepted or undone.
     *
     * @param array<string, mixed> $form
     */
    public function follow(array $form, string $actorUri): void
    {
        $this->forgetRequest($form, 'following', $actorUri);
        $activity = $this->postActivity(['type' => 'Follow', 'object' => $actorUri, 'to' => $actorUri], $form);
        $this->rememberRequest($form, 'following', $actorUri, (string)$activity['id']);
    }

    /**
     * Stops following a remote actor, undoing the Follow it was sent.
     *
     * @param array<string, mixed> $form
     */
    public function unfollow(array $form, string $actorUri): void
    {
        $this->removeFollowing($form, $actorUri);
        $followId = $this->requestId($form, 'following', $actorUri) ?? $this->getFormActorUri($form) . '#follow';
        $this->forgetRequest($form, 'following', $actorUri);

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
     *
     * @param array<string, mixed> $form
     */
    public function acceptFollow(array $form, string $actorUri, ?string $followId): bool
    {
        $pendingId = $this->requestId($form, 'following', $actorUri);
        if ($pendingId === null || ($followId !== null && $followId !== $pendingId)) {
            return false;
        }
        if (!in_array($actorUri, $this->getFollowing($form), true)) {
            $this->addFollowing($form, $actorUri);
        }

        return true;
    }

    /** @param array<string, mixed> $form */
    public function rejectFollow(array $form, string $actorUri): void
    {
        $this->forgetRequest($form, 'following', $actorUri);
        $this->removeFollowing($form, $actorUri);
    }

    /**
     * The actors the form asked to follow that have not accepted yet.
     *
     * @param array<string, mixed> $form
     *
     * @return list<string>
     */
    public function getPendingFollowing(array $form): array
    {
        return array_values(array_diff(
            array_map(fn ($request) => $request['actor'], $this->requests($form, 'following')),
            $this->getFollowing($form)
        ));
    }

    /**
     * Takes in a Follow sent to the form: accepted right away unless the profile asks to approve followers, and kept as a request while it is not.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $follow
     */
    public function receiveFollow(array $form, array $follow): void
    {
        $actorUri = is_string($follow['actor'] ?? null) ? $follow['actor'] : '';
        $followId = is_string($follow['id'] ?? null) ? $follow['id'] : '';
        if ($actorUri === '' || $followId === '') {
            throw new \Exception('A Follow needs an actor and an id');
        }
        $this->forgetRequest($form, 'followers', $actorUri);
        $this->rememberRequest($form, 'followers', $actorUri, $followId);

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
     *
     * @param array<string, mixed> $form
     */
    public function acceptFollower(array $form, string $actorUri): void
    {
        $followId = $this->requestId($form, 'followers', $actorUri);
        if ($followId === null) {
            throw new \Exception("No pending follow request from {$actorUri}");
        }
        $this->postActivity(['type' => 'Accept', 'object' => $this->incomingFollow($form, $actorUri, $followId), 'to' => $actorUri], $form);
        if (!in_array($actorUri, $this->getFollowers($form), true)) {
            $this->addFollower($form, $actorUri);
        }
        $this->forgetRequest($form, 'followers', $actorUri);
    }

    /**
     * Refuses a pending Follow request, or removes a follower, and tells the remote actor.
     *
     * @param array<string, mixed> $form
     */
    public function rejectFollower(array $form, string $actorUri): void
    {
        $followId = $this->requestId($form, 'followers', $actorUri) ?? $actorUri . '#follow';
        $this->forgetRequest($form, 'followers', $actorUri);
        $this->removeFollower($form, $actorUri);
        $this->postActivity(['type' => 'Reject', 'object' => $this->incomingFollow($form, $actorUri, $followId), 'to' => $actorUri], $form);
    }

    /**
     * Forgets everything an actor asked: its pending request and its place among the followers.
     *
     * @param array<string, mixed> $form
     */
    public function undoFollow(array $form, string $actorUri): void
    {
        $this->forgetRequest($form, 'followers', $actorUri);
        $this->removeFollower($form, $actorUri);
    }

    /**
     * The actors waiting for the form to accept their Follow.
     *
     * @param array<string, mixed> $form
     *
     * @return list<string>
     */
    public function getFollowRequests(array $form): array
    {
        return array_values(array_diff(
            array_map(fn ($request) => $request['actor'], $this->requests($form, 'followers')),
            $this->getFollowers($form)
        ));
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array{id: string, type: string, actor: string, object: string}
     */
    protected function incomingFollow(array $form, string $actorUri, string $followId): array
    {
        return ['id' => $followId, 'type' => 'Follow', 'actor' => $actorUri, 'object' => $this->getFormActorUri($form)];
    }

    /** @param array<string, mixed> $form */
    protected function rememberRequest(array $form, string $collection, string $actorUri, string $followId): void
    {
        $this->tripleStore->create(
            $this->getFormCollectionUri($form, $collection),
            self::$AS_PREFIX . 'Follow',
            json_encode(['actor' => $actorUri, 'id' => $followId], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            '',
            ''
        );
    }

    /** @param array<string, mixed> $form */
    protected function requestId(array $form, string $collection, string $actorUri): ?string
    {
        foreach ($this->requests($form, $collection) as $request) {
            if ($request['actor'] === $actorUri) {
                return $request['id'];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $form */
    protected function forgetRequest(array $form, string $collection, string $actorUri): void
    {
        foreach ($this->requests($form, $collection) as $raw => $request) {
            if ($request['actor'] === $actorUri) {
                $this->tripleStore->delete($this->getFormCollectionUri($form, $collection), self::$AS_PREFIX . 'Follow', (string)$raw, '', '');
            }
        }
    }

    /**
     * The Follow activities a collection holds: sent ones under `following`, received ones under `followers`, keyed by their stored value.
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, array{actor: string, id: string}>
     */
    protected function requests(array $form, string $collection): array
    {
        $requests = [];
        $triples = $this->tripleStore->getMatching($this->getFormCollectionUri($form, $collection), self::$AS_PREFIX . 'Follow', null, '', '');
        foreach ($triples as $triple) {
            $value = is_string($triple['value'] ?? null) ? $triple['value'] : '';
            $request = json_decode($value, true);
            if (is_array($request) && is_string($request['actor'] ?? null) && is_string($request['id'] ?? null)) {
                $requests[$value] = ['actor' => $request['actor'], 'id' => $request['id']];
            }
        }

        return $requests;
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return list<string>
     */
    public function getFollowers(array $form): array
    {
        $followers = $this->tripleStore->getMatching($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', null, '', '');

        return array_values(array_filter(array_map(fn ($f) => is_string($f['value']) ? $f['value'] : '', $followers)));
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return list<string>
     */
    public function getFollowing(array $form): array
    {
        $following = $this->tripleStore->getMatching($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', null, '', '');

        return array_values(array_filter(array_map(fn ($f) => is_string($f['value']) ? $f['value'] : '', $following)));
    }

    /** @param array<string, mixed> $form */
    public function addFollowing(array $form, string $actorUri): void
    {
        $this->tripleStore->create($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    /** @param array<string, mixed> $form */
    public function addFollower(array $form, string $actorUri): void
    {
        $this->tripleStore->create($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    /** @param array<string, mixed> $form */
    public function removeFollowing(array $form, string $actorUri): void
    {
        $this->tripleStore->delete($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    /** @param array<string, mixed> $form */
    public function removeFollower(array $form, string $actorUri): void
    {
        $this->tripleStore->delete($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    /**
     * An entry as the public object the outbox and the notifications carry.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    public function entryObject(array $form, array $entry): array
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
     *
     * @param array<string, mixed> $form
     */
    public function announceProfile(array $form): void
    {
        if (!$this->hasActor($form)) {
            return;
        }
        $actor = $this->getActor($form);
        $context = $actor['@context'];
        unset($actor['@context']);

        try {
            $this->postActivity([
                '@context' => $context,
                'type' => 'Update',
                'object' => $actor,
                'to' => [self::PUBLIC],
                'cc' => [$this->getFormCollectionUri($form, 'followers')],
            ], $form);
        } catch (\Exception $e) {
            error_log('ActivityPub: ' . $e->getMessage());
        }
    }

    /**
     * Tells the followers an entry was created, updated or deleted, a deleted one as a Tombstone; a follower that cannot be reached is logged, not fatal.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     */
    public function notifyFollowers(array $form, array $entry, string $activityType): void
    {
        try {
            $object = $this->entryObject($form, $entry);
            if ($activityType === 'Delete') {
                $object = ['id' => $object['id'], 'type' => 'Tombstone', 'formerType' => $object['type'] ?? 'Object'];
            }
            $this->postActivity([
                'type' => $activityType,
                'object' => $object,
                'to' => [self::PUBLIC],
                'cc' => [$this->getFormCollectionUri($form, 'followers')],
            ], $form);
        } catch (\Exception $e) {
            error_log('ActivityPub: ' . $e->getMessage());
        }
    }
}
