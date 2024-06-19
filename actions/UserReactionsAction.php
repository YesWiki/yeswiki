<?php
/**
 * Show all user's reaction.
 */
use YesWiki\Core\Service\ReactionManager;
use YesWiki\Core\YesWikiAction;

class UserReactionsAction extends YesWikiAction
{
    public function run()
    {
        if ($user = $this->wiki->GetUser()) {
            $userReactions = $this->wiki->services->get(ReactionManager::class)->getReactions('', [], $user['name']);

            return $this->render('@core/user-reactions.twig', [
                'userReactions' => $userReactions,
            ]);
        } else {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'info',
                'message' => _t('REACTION_LOGIN_TO_SEE_YOUR_REACTION'),
            ]);
        }
    }
}
