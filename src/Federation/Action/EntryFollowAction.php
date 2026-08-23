<?php

namespace YesWiki\Federation\Action;

use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Federation\Service\ActivityPubService;
use YesWiki\Federation\Service\WebfingerService;
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
        $webfingerService = $this->getService(WebfingerService::class);

        $formId = $this->arguments['id'];
        $form = $this->getService(FormManager::class)->getOne($formId);
        if (empty($form)) {
            return '';
        }

        $post = $this->getRequest()->request;
        if ($post->has('actor_handle') && $post->get('form_id') == $formId) {
            $formActorUri = $activityPubService->getFormActorUri($form);

            $interactionUrl = $webfingerService->getInteractionUrl((string)$post->get('actor_handle', ''), $formActorUri);

            return $this->getService(Redirector::class)->redirect($interactionUrl);
        }

        if (!$activityPubService->isEnabled($form)) {
            return '';
        }

        return $this->render('@core/follow.twig', [
            'form' => $form,
        ]);
    }
}
