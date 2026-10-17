<?php

namespace YesWiki\Federation\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Federation\Service\ActivityPubInbox;
use YesWiki\Federation\Service\ActivityPubProfiles;
use YesWiki\Federation\Service\ActivityPubService;
use YesWiki\Federation\Service\HttpSignatureService;
use YesWiki\Federation\Service\WebFingerService;

/** What other fediverse servers read and post: each form's actor, its collections, its inbox, and the WebFinger lookup that leads to it. */
class ActivityPubApiController extends YesWikiController
{
    private const HEADERS = ['Content-Type' => HttpSignatureService::CONTENT_TYPE];

    #[Route('/api/forms/{formId}/actor', methods: ['GET'], options: ['acl' => ['public']])]
    public function actor(string $formId): ApiResponse
    {
        return new ApiResponse($this->activityPub()->getActor($this->actorForm($formId)), Response::HTTP_OK, self::HEADERS);
    }

    #[Route('/api/forms/{formId}/actor/followers', methods: ['GET'], options: ['acl' => ['public']])]
    public function followers(string $formId): ApiResponse
    {
        $form = $this->actorForm($formId);

        return $this->collection($this->activityPub()->getFormCollectionUri($form, 'followers'), $this->activityPub()->getFollowers($form));
    }

    #[Route('/api/forms/{formId}/actor/following', methods: ['GET'], options: ['acl' => ['public']])]
    public function following(string $formId): ApiResponse
    {
        $form = $this->actorForm($formId);

        return $this->collection($this->activityPub()->getFormCollectionUri($form, 'following'), $this->activityPub()->getFollowing($form));
    }

    #[Route('/api/forms/{formId}/actor/inbox', methods: ['POST'], options: ['acl' => ['public']])]
    public function inbox(string $formId, Request $request): ApiResponse
    {
        $form = $this->actorForm($formId);
        $activityPub = $this->activityPub();

        try {
            $verifiedActor = $this->getService(HttpSignatureService::class)->verifySignature(
                $request,
                $activityPub->signer($form),
                (string)parse_url($activityPub->getFormActorUri($form) . '/inbox', PHP_URL_PATH)
            );
        } catch (\Exception $e) {
            return new ApiResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED, self::HEADERS);
        }

        $activity = json_decode($request->getContent(), true);
        if (!is_array($activity)) {
            return new ApiResponse(['error' => 'Malformed activity'], Response::HTTP_BAD_REQUEST, self::HEADERS);
        }

        try {
            $this->getService(ActivityPubInbox::class)->processActivity($activity, $form, $verifiedActor);
        } catch (\Exception $e) {
            return new ApiResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN, self::HEADERS);
        }

        return new ApiResponse(null, Response::HTTP_ACCEPTED, self::HEADERS);
    }

    #[Route('/api/forms/{formId}/actor/outbox', methods: ['GET'], options: ['acl' => ['public']])]
    public function outbox(string $formId): ApiResponse
    {
        $form = $this->actorForm($formId);
        $activityPub = $this->activityPub();
        $actorUri = $activityPub->getFormActorUri($form);
        $entries = array_values($this->getService(EntryManager::class)->search(['id' => $form['id']], true));

        $items = [];
        foreach ($entries as $entry) {
            try {
                $object = $activityPub->entryObject($form, $entry);
            } catch (\Exception $e) {
                continue;
            }
            $created = is_string($entry['created_at'] ?? null) ? strtotime($entry['created_at']) : false;
            $items[] = [
                'id' => $object['id'] . '#create',
                'type' => 'Create',
                'actor' => $actorUri,
                'published' => date(DATE_ATOM, $created === false ? time() : $created),
                'object' => $object,
                'to' => [ActivityPubService::PUBLIC],
                'cc' => [$activityPub->getFormCollectionUri($form, 'followers')],
            ];
        }

        return new ApiResponse([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'OrderedCollection',
            'id' => $activityPub->getFormCollectionUri($form, 'outbox'),
            'totalItems' => count($items),
            'orderedItems' => $items,
        ], Response::HTTP_OK, self::HEADERS);
    }

    #[Route('/api/webfinger', methods: ['GET'], options: ['acl' => ['public']])]
    public function webfinger(Request $request): ApiResponse
    {
        $handle = preg_replace('/^acct:/', '', $request->query->getString('resource'));
        try {
            $matches = $this->getService(WebFingerService::class)->splitHandle((string)$handle);
        } catch (\Exception $e) {
            throw new NotFoundHttpException();
        }

        $formId = $this->getService(ActivityPubProfiles::class)->formIdForUsername((string)($matches['user'] ?? ''));
        $form = $formId === null ? null : $this->getService(FormManager::class)->getOne($formId);
        $activityPub = $this->activityPub();
        if (!is_array($form) || !$activityPub->hasActor($form)) {
            throw new NotFoundHttpException();
        }

        return new ApiResponse(
            $this->getService(WebFingerService::class)->formatLocalActor((string)$handle, $activityPub->getFormActorUri($form), $activityPub->getActor($form)['url']),
            Response::HTTP_OK,
            ['Content-Type' => 'application/jrd+json']
        );
    }

    /**
     * @param list<string> $items
     */
    private function collection(string $id, array $items): ApiResponse
    {
        return new ApiResponse([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Collection',
            'id' => $id,
            'totalItems' => count($items),
            'items' => $items,
        ], Response::HTTP_OK, self::HEADERS);
    }

    /**
     * The form behind an actor address, or a 404 when it publishes none.
     *
     * @return array<string, mixed>
     */
    private function actorForm(string $formId): array
    {
        $form = ctype_digit($formId) ? $this->getService(FormManager::class)->getOne($formId) : null;
        if (!is_array($form) || !$this->activityPub()->hasActor($form)) {
            throw new NotFoundHttpException();
        }

        return $form;
    }

    private function activityPub(): ActivityPubService
    {
        return $this->getService(ActivityPubService::class);
    }
}
