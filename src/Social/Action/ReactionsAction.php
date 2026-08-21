<?php

namespace YesWiki\Social\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Social\Service\ReactionManager;
use YesWiki\Social\Service\ReactionsFormatter;

class ReactionsAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{reactions}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'reactions';
    }

    public function components(): array
    {
        return [
            Component::for('reactions')
                ->category(Category::Forms)
                ->label(_t('AB_REACTION_LABEL'))
                ->icon('thumb-up')
                ->description(_t('AB_REACTION_DESC'))
                ->settings(
                    Setting::text('title')
                        ->label(_t('AB_REACTION_TITLE_LABEL'))
                        ->suggests(_t('REACTION_SHARE_YOUR_REACTION'))
                        ->required(),
                    Setting::number('maxreaction')
                        ->label(_t('AB_REACTION_MAXREACTION_LABEL'))
                        ->default(1)
                        ->suggests(1),
                    Setting::reaction('choices')
                        ->raw('btn-label-add', _t('AB_REACTION_ADD_REACTION'))
                        ->subSettings(
                            Setting::text('label')
                            ->label(_t('AB_REACTION_NAME')),
                            Setting::icon('image')
                            ->label(_t('AB_REACTION_ICON')),
                        ),
                ),
        ];
    }

    public function formatArguments($args)
    {
        return [
            'labels' => (!empty($args['labels']) && is_string($args['labels']))
                ? $args['labels']
                : implode(',', array_map('_t', ReactionManager::DEFAULT_LABELS_T)),
            'images' => (!empty($args['images']) && is_string($args['images']))
                ? $args['images']
                : '',
            'title' => $args['title'] ?? _t(ReactionManager::DEFAULT_TITLE_T),
            'maxreaction' => !empty($args['maxreaction']) ? $args['maxreaction'] : ReactionManager::DEFAULT_MAX_REACTIONS,
        ];
    }

    /**
     * @return string the reaction buttons, or an error message when `title` is missing
     */
    public function run()
    {
        if (empty($this->arguments['title'])) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('REACTION_TITLE_PARAM_NEEDED'),
            ]);
        }
        $idreaction = \URLify::slug($this->arguments['title']);

        $user = $this->getService(AuthenticationService::class)->getLoggedUser();
        $username = empty($user['name']) ? '' : $user['name'];

        $reactionsFormatter = $this->getService(ReactionsFormatter::class);
        list('labels' => $labels, 'ids' => $ids) = $reactionsFormatter->formatReactionsLabels(
            $this->arguments['labels'],
            empty($this->arguments['labels']) ? ReactionManager::DEFAULT_IDS : null,
        );

        $images = $reactionsFormatter->formatImages(
            $ids,
            $this->arguments['images'],
            ReactionManager::DEFAULT_IMAGES
        );

        $pageTag = $this->getService(PageContext::class)->getTag();
        list('reactions' => $reactionItems, 'userReactions' => $userReactions) = $reactionsFormatter->getReactionItems(
            $pageTag,
            $username,
            $idreaction,
            $ids,
            $labels,
            $images
        );

        return $this->render('@core/reactions.twig', [
            'reactionId' => $idreaction,
            // run() returns early when there is no title, so there is nothing to fall back to here
            'title' => $this->arguments['title'],
            'connected' => !empty($username),
            'reactionItems' => $reactionItems,
            'userName' => $username,
            'userReaction' => $userReactions,
            'maxReaction' => $this->arguments['maxreaction'],
            'pageTag' => $pageTag,
        ]);
    }
}
