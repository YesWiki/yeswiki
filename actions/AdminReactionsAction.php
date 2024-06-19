<?php
/**
 * Admin all reactions.
 */
use YesWiki\Core\Service\ReactionManager;
use YesWiki\Core\YesWikiAction;

class AdminReactionsAction extends YesWikiAction
{
    public function run()
    {
        if ($this->wiki->UserIsAdmin()) {
            $allReactions = $this->wiki->services->get(ReactionManager::class)->getReactions();
            foreach ($allReactions as $k => $reactions) {
                usort($reactions['reactions'], function ($a, $b) { // sort by user
                    return strnatcasecmp($a['user'], $b['user']);
                });
            }

            return $this->render('@core/admin-reactions-table.twig', [
                'reactions' => $allReactions,
            ]);
        } else {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'info',
                'message' => _t('REACTION_CONNECT_AS_ADMIN'),
            ]);
        }
    }
}
