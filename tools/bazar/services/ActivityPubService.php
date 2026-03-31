<?php

namespace YesWiki\Bazar\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\TripleStore;

class ActivityPubService
{
    public static $AS_PREFIX = 'https://www.w3.org/ns/activitystreams#';

    protected $params;
    protected $httpClient;
    protected $webfingerService;
    protected $container;
    protected $httpSignatureService;
    protected $semanticTransformer;
    protected $tripleStore;

    public function __construct(ParameterBagInterface $params, WebfingerService $webfingerService, ContainerInterface $container, HttpSignatureService $httpSignatureService, SemanticTransformer $semanticTransformer, TripleStore $tripleStore)
    {
        $this->params = $params;
        $this->httpClient = HttpClient::create();
        $this->webfingerService = $webfingerService;
        $this->container = $container;
        $this->httpSignatureService = $httpSignatureService;
        $this->semanticTransformer = $semanticTransformer;
        $this->tripleStore = $tripleStore;
    }

    public function isEnabled($form)
    {
        return $form['bn_activitypub_enable'] === '1';
    }
    
    public function getFormActorUri($form) {
        return $this->params->get('base_url') . "api/forms/" . $form['bn_id_nature'] . "/actor";
    }

    public function getFormCollectionUri($form, $collectionType) {
        return $this->params->get('base_url') . "api/forms/" . $form['bn_id_nature'] . "/actor" . "/" . $collectionType;
    }

    public function getActor($form) {
        $actorUrl = $this->getFormActorUri($form);

        $actor = [
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $actorUrl ,
            "type" => "Application",
            "name" => $form['bn_label_nature'],
            "preferredUsername" => $form['bn_activitypub_username'],
            "inbox" => $actorUrl . "/inbox",
            "outbox" => $actorUrl . "/outbox",
            "followers" => $actorUrl . "/followers",
            "following" => $actorUrl . "/following",
            "publicKey" => [
                "id" => $actorUrl . "#main-key",
                "owner" => $actorUrl,
                "publicKeyPem" => $form['bn_activitypub_public_key']
            ]
        ];

        return $actor;
    }

    public function getActorInbox(string $actorUri) : string {
        $response = $this->httpClient->request('GET', $actorUri, [
            'headers' => [
                'Accept' => 'application/ld+json'
            ]
        ]);

        $actor = json_decode($response->getContent(), true);

        return $actor['inbox'];
    }

    protected function getRecipients($form, $activity) {
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

    public function postActivity($activity, $form) {
        $activity['@context'] = "https://www.w3.org/ns/activitystreams";
        $activity['actor'] = $this->getFormActorUri($form);
        $activity['id'] = $activity['actor'] . '#' . strtolower($activity['type']); // Transient URI, allowed by ActivityPub specs

        $recipientsUris = $this->getRecipients($form, $activity);

        foreach ($recipientsUris as $recipientUri) {
            $inboxUri = $this->getActorInbox($recipientUri);

            $signatureHeaders = $this->httpSignatureService->generateSignature($activity, $inboxUri, $form);

            $fullHeaders = array_merge($signatureHeaders, [ 'Content-Type' => 'application/activity+json']);

            $this->httpClient->request('POST', $inboxUri, [
                'body' => json_encode($activity, JSON_UNESCAPED_SLASHES),
                'headers' => $fullHeaders
            ]);
        }
    }

    public function processActivity($activity, $form) {
        switch($activity['type']) {
            case 'Accept': {
                if ($activity['object']['type'] === 'Follow') {
                    $this->addFollowing($form, $activity['actor']);
                }
                break;
            }

            case 'Follow': {
                $this->addFollower($form, $activity['actor']);

                // Send back an Accept activity
                $this->postActivity([
                    "type" => "Accept",
                    "object" => $activity,
                    "to" => $activity['actor'],
                ], $form);

                break;
            }

            case 'Undo': {
                if ($activity['object']['type'] === 'Follow') {
                    $this->removeFollower($form, $activity['actor']);
                }

                break;
            }
            
            case 'Create': {
                $object = $activity['object'];
                $sourceUrl = $object['id'] ?? null;
                $entryManager = $this->container->get(EntryManager::class);
                $entryManager->create($form['bn_id_nature'], $object, true, $sourceUrl);
                break;
            }

            case 'Update': {
                $object = $activity['object'];
                $sourceUrl = $object['id'] ?? null;
                if ($sourceUrl) {
                    $triples = $this->tripleStore->getMatching(null, TripleStore::SOURCE_URL_URI, $sourceUrl, '=', '=', '=');
                    if (!empty($triples)) {
                        $tag = $triples[0]['resource'];
                        $entryManager = $this->container->get(EntryManager::class);
                        $entryManager->update($tag, $object, true);
                    }
                }
                break;
            }

            case 'Delete': {
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
    }

    public function getFollowers($form) {
        $followers = $this->tripleStore->getMatching($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', null, '', '');

        return array_map(fn($f) => $f['value'], $followers);
    }

    public function getFollowing($form) {
        $following = $this->tripleStore->getMatching($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', null, '', '');

        return array_map(fn($f) => $f['value'], $following);
    }

    public function addFollowing($form, $actorUri) {
        $this->tripleStore->create($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    public function addFollower($form, $actorUri) {
        $this->tripleStore->create($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    public function removeFollowing($form, $actorUri) {
        $this->tripleStore->delete($this->getFormCollectionUri($form, 'following'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    public function removeFollower($form, $actorUri) {
        $this->tripleStore->delete($this->getFormCollectionUri($form, 'followers'), self::$AS_PREFIX . 'items', $actorUri, '', '');
    }

    public function notifyFollowers($form, $entry, $activityType) {
        $object = $this->semanticTransformer->convertToSemanticData($form, $entry, true);
        unset($object['@context']);

        $this->postActivity([
            'type' => $activityType,
            'object' => $object,
            'to' => $this->getFormCollectionUri($form, 'followers'),
        ], $form);
    }
}
