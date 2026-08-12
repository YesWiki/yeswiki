<?php

namespace YesWiki\Content\Action;

/*
 * Admin all reactions.
 */
use YesWiki\Content\Service\ReactionManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;

class AdminReactionsAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{adminreactions}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'adminreactions';
    }

    public function components(): array
    {
        return [
            Component::for('adminreactions')
                ->category(Category::Admin)
                ->label(_t('AB_ADMINREACTIONS_LABEL'))
                ->icon('thumb-up')
                ->hint(_t('AB_ADMINREACTIONS_HINT')),
        ];
    }

    public function run()
    {
        if ($this->getService(AclService::class)->isAdmin()) {
            $allReactions = $this->getService(ReactionManager::class)->getReactions();
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
