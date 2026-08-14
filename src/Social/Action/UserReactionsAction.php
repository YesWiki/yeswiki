<?php

namespace YesWiki\Social\Action;

/*
 * Show all user's reaction.
 */
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Social\Service\ReactionManager;

class UserReactionsAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{userreactions}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'userreactions';
    }

    public function components(): array
    {
        return [
            Component::for('userreactions')
                ->category(Category::Admin)
                ->label(_t('AB_USERREACTIONS_LABEL'))
                ->icon('thumb-up')
                ->hint(_t('AB_USERREACTIONS_HINT')),
        ];
    }

    public function run()
    {
        if ($user = $this->getService(AuthenticationService::class)->getLoggedUser()) {
            $userReactions = $this->getService(ReactionManager::class)->getReactions('', [], $user['name']);

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
