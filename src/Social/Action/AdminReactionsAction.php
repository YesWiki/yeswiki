<?php

namespace YesWiki\Social\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Social\Service\ReactionManager;

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

    /**
     * @return string the reactions table, or an invitation to log in as an admin
     */
    public function run()
    {
        if ($this->getService(AclService::class)->isAdmin()) {
            $allReactions = $this->getService(ReactionManager::class)->getReactions();
            foreach (array_keys($allReactions) as $k) {
                // sort in place: sorting the `foreach` copy left the table in insertion order
                usort($allReactions[$k]['reactions'], function ($a, $b) {
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
