<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use YesWiki\Core\Service\TripleStore;

class ActivityPubService
{
    public static $AS_PREFIX = 'https://www.w3.org/ns/activitystreams#';

    protected $params;
    protected $httpClient;
    protected $webfingerService;
    protected $httpSignatureService;
    protected $semanticTransformer;
    protected $tripleStore;
    protected $ssrfUrlValidator;

    public function __construct(ParameterBagInterface $params, WebfingerService $webfingerService, HttpSignatureService $httpSignatureService, SemanticTransformer $semanticTransformer, TripleStore $tripleStore, SsrfUrlValidator $ssrfUrlValidator)
    {
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
                continue;
            }
            if ($recipient === $this->getFormCollectionUri($form, 'followers')) {
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
        $activity['id'] = $activity['actor'] . '#' . strtolower($activity['type']);

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

            $body = $response->getContent(false);
            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \Exception("Failed to send activity to $inboxUri (HTTP $statusCode): $body");
            }
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
}
