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
        $output = '<h2>Vos réactions</h2>';
        if ($user = $this->wiki->GetUser()) {
            $userReactions = $this->wiki->services->get(ReactionManager::class)->getUserReactions(
                $user['name']
            );
            foreach ($userReactions as $userReaction) {
                unset($userReaction['user']);
                $output .= implode(' - ', $userReaction).'<br />';
            }
        } else {
            return $this->render('@templates/alert-message.twig', [
                'type'=>'info',
                'message'=> 'Veuillez vous connecter pour voir vos réactions.'
            ]);
        }
        return $output;
    }
}
