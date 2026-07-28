<?php

namespace YesWiki\Content\Action;
/**
 * Admin all reactions.
 */
use YesWiki\Content\Service\ReactionManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class AdminReactionsAction extends YesWikiAction implements RegisteredAction
{
    /** `{{adminreactions}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'adminreactions';
    }

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
        }

        return $this->render('@core/alert-message.twig', [
            'type' => 'info',
            'message' => _t('REACTION_CONNECT_AS_ADMIN'),
        ]);
    }
}
