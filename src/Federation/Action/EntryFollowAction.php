<?php

namespace YesWiki\Federation\Action;

use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Federation\Service\ActivityPubService;
use YesWiki\Federation\Service\WebFingerService;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\Redirector;

class EntryFollowAction extends YesWikiAction implements RegisteredAction
{
    /** `{{entryfollow}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'entryfollow';
    }

    public function run(): string
    {
        $activityPubService = $this->getService(ActivityPubService::class);

        $formId = $this->arguments['id'];
        $form = $this->getService(FormManager::class)->getOne($formId);
        if (empty($form) || !$activityPubService->hasActor($form)) {
            return '';
        }

        $formHandle = $activityPubService->handle($form);
        $error = null;

        $post = $this->getRequest()->request;
        if ($post->has('actor_handle') && $post->get('form_id') == $formId) {
            try {
                $interactionUrl = $this->getService(WebFingerService::class)->getInteractionUrl(
                    trim((string)$post->get('actor_handle', '')),
                    $activityPubService->getFormActorUri($form)
                );
            } catch (\Exception $e) {
                $interactionUrl = null;
            }
            if (!empty($interactionUrl)) {
                return $this->getService(Redirector::class)->redirect($interactionUrl);
            }
            $error = _t('BAZ_FOLLOW_SEARCH_HANDLE', ['handle' => $formHandle]);
        }

        return $this->render('@core/follow.twig', [
            'form' => $form,
            'formHandle' => $formHandle,
            'error' => $error,
            'routes' => $this->getService(AclService::class)->isAdmin() ? $activityPubService->routesToCheck($form) : null,
        ]);
    }
}
