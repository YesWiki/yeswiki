<?php
/**
 * Show all user's reaction
 */
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\ReactionManager;

class UserReactionsAction extends YesWikiAction
{
    public function run()
    {
        if ($user = $this->wiki->GetUser()) {
            $userReactions = $this->wiki->services->get(ReactionManager::class)->getReactions('', [], $user['name']);
            return $this->render('templates/user-reactions.twig', [
                'userReactions'=> $userReactions,
            ]);
        } else {
            return $this->render('@templates/alert-message.twig', [
                'type'=>'info',
                'message'=> _t('REACTION_LOGIN_TO_SEE_YOUR_REACTION')
            ]);
        }
    }
}
