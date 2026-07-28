<?php

use YesWiki\Content\Service\ActivityPubService;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\WebfingerService;
use YesWiki\Core\YesWikiAction;

class BazarFollowAction extends YesWikiAction
{
    public function run()
    {
        $activityPubService = $this->getService(ActivityPubService::class);
        $webfingerService = $this->getService(WebfingerService::class);

        $formId = $this->arguments['id'];
        $form = $this->getService(FormManager::class)->getOne($formId);

        $post = $this->getRequest()->request;
        if ($post->has('actor_handle') && $post->get('form_id') == $formId) {
            $formActorUri = $activityPubService->getFormActorUri($form);

            $interactionUrl = $webfingerService->getInteractionUrl($post->get('actor_handle'), $formActorUri);

            return $this->wiki->redirect($interactionUrl);
        }

        $activityPubEnabled = $activityPubService->isEnabled($form);

        if ($activityPubEnabled) {
            return $this->render('@core/follow.twig', [
                'form' => $form,
            ]);
        }
    }
}
