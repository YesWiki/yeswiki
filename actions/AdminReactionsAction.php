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
        if ($this->wiki->UserIsAdmin()) {
            $allReactions = $this->wiki->services->get(ReactionManager::class)->getAllReactions();
            ksort($allReactions);
            foreach ($allReactions as $reactions) {
                usort($reactions, function ($a, $b) { // sort by user
                    return strnatcasecmp($a['user'], $b['user']);
                });
            }
            return $this->render('@templates/admin-reactions-table.twig', [
                'reactions'=> $allReactions,
            ]);
        } else {
            return $this->render('@templates/alert-message.twig', [
                'type'=>'info',
                'message'=> _t('REACTION_CONNECT_AS_ADMIN')
            ]);
        }
    }
}
