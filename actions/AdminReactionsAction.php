<?php
/**
 * Admin all reactions
 */
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\ReactionManager;

class AdminReactionsAction extends YesWikiAction
{
    public function run()
    {
        $output = '<h2>Administrer les réactions</h2>';
        if ($this->wiki->UserIsAdmin()) {
            $allReactions = $this->wiki->services->get(ReactionManager::class)->getAllReactions();
            foreach ($allReactions as $reactions) {
                foreach ($reactions as $reaction) {
                    $output .= implode(' - ', $reaction).'<br />';
                }
            }
        } else {
            return $this->render('@templates/alert-message.twig', [
                'type'=>'info',
                'message'=> 'Veuillez vous connecter en tant qu\'admin pour administrer les réactions.'
            ]);
        }
        return $output;
    }
}
