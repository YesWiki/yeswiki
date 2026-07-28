<?php

namespace YesWiki\Content\Action;
/**
 * Show all user's reaction.
 */
use YesWiki\Content\Service\ReactionManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class UserReactionsAction extends YesWikiAction implements RegisteredAction
{
    /** `{{userreactions}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'userreactions';
    }

    public function run()
    {
        if ($user = $this->wiki->GetUser()) {
            $userReactions = $this->wiki->services->get(ReactionManager::class)->getReactions('', [], $user['name']);

            return $this->render('@core/user-reactions.twig', [
                'userReactions' => $userReactions,
            ]);
        }

        return $this->render('@core/alert-message.twig', [
            'type' => 'info',
            'message' => _t('REACTION_LOGIN_TO_SEE_YOUR_REACTION'),
        ]);
    }
}
