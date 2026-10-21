<?php

use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\WebfingerService;
use YesWiki\Core\YesWikiAction;

class BazarFollowAction extends YesWikiAction
{
    public function run()
    {
        $activityPubService = $this->getService(ActivityPubService::class);
        $webfingerService = $this->getService(WebfingerService::class);

        $formId = $this->arguments['id'];
        $form = $this->getService(FormManager::class)->getOne($formId);

        if (empty($form) || !$activityPubService->hasActor($form)) {
            return;
        }

        $formHandle = $activityPubService->handle($form);
        $error = null;

        $post = $this->getRequest()->request;
        if ($post->has('actor_handle') && $post->get('form_id') == $formId) {
            try {
                $interactionUrl = $webfingerService->getInteractionUrl(trim($post->get('actor_handle')), $activityPubService->getFormActorUri($form));
            } catch (Exception $e) {
                $interactionUrl = null;
            }
            if (!empty($interactionUrl)) {
                return $this->wiki->redirect($interactionUrl);
            }
            $error = _t('BAZ_FOLLOW_SEARCH_HANDLE', ['handle' => $formHandle]);
        }

        return $this->render('@bazar/follow.twig', [
            'form' => $form,
            'formHandle' => $formHandle,
            'error' => $error,
            'routes' => $this->wiki->UserIsAdmin() ? $activityPubService->routesToCheck($form) : null,
        ]);
    }
}
