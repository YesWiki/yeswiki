<?php
/**
 * Allow signed-in users to react with icon, emojis or pictures on the page
 */
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\ReactionManager;

class ReactionsAction extends YesWikiAction
{
    protected $images;
    protected $labels;

    public function formatArguments($args)
    {
        return [
            'labels' => !empty($args['labels'])
                ? array_map('trim', explode(',', $args['labels']))
                : array_map('_t', ReactionManager::DEFAULT_LABELS_T),
            'images' => !empty($args['images'])
                ? array_map('trim', explode(',', $args['images']))
                : ReactionManager::DEFAULT_IMAGES,
            'title' => $args['title'] ?? _t(ReactionManager::DEFAULT_TITLE_T),
            'maxreaction' => !empty($args['maxreaction']) ? $args['maxreaction'] : ReactionManager::DEFAULT_MAX_REACTIONS,
        ];
    }

    public function formatReactionItems($idreaction)
    {
        $reactionItems = [];
        $allReaction = $this->wiki->services->get(ReactionManager::class)->getReactions($this->wiki->getPageTag(), [$idreaction]);
        foreach ($this->labels as $k => $label) {
            $id = URLify::slug($label);
            if (empty($this->images[$k])) {
                return $this->render('@templates/alert-message.twig', [
                    'type'=>'danger',
                    'message'=> 'ERROR : no image for reaction '.$label.'.'
                ]);
            } else {
                if (preg_match("/.(gif|jpeg|png|jpg|svg|webp)$/i", $this->images[$k]) == 1) { //image
                    $image = '<img class="img-responsive" src="'.$this->images[$k].'" alt="reaction image '.$id.'" />';
                } elseif (preg_match('/\p{S}/u', $this->images[$k]) == 1) { //emoji
                    $image = '<span class="reaction-emoji">'.$this->images[$k].'</span>';
                } elseif (preg_match("/^(fa[srb]? fa-*)/i", $this->images[$k]) == 1) { //class
                    $image = '<i class="reaction-fa-icons '.$this->images[$k].'"></i>';
                } else { // not good
                    return $this->render('@templates/alert-message.twig', [
                        'type'=>'danger',
                        'message'=> 'ERROR : image must be emoji or image file or font-awesome class for reaction '.$label.'.'
                    ]);
                }
            }
            $type_count = 0;
            $uniqueId = $idreaction.'|'.$this->wiki->getPageTag();
            if (!empty($allReaction[$uniqueId])) {
                foreach ($allReaction[$uniqueId]['reactions'] as $r) {
                    if (array_search($id, $r)) {
                        $type_count++;
                    }
                }
            }
            $reactionItems[$k] = [
                'id' => $id,
                'image' => $image,
                'label' => $label,
                'nbReactions' => $type_count
            ];
        }
        return $reactionItems;
    }
    public function run()
    {
        $this->labels = $this->arguments['labels'];
        $this->images = $this->arguments['images'];
        $title = $this->arguments['title'];
        $maxReaction = $this->arguments['maxreaction'];
        
        if (empty($title)) {
            return $this->render("@templates/alert-message.twig", [
                'type' => 'danger',
                'message' => _t('REACTION_TITLE_PARAM_NEEDED')
            ]);
            if (empty($GLOBALS['nbreactions'])) {
                $GLOBALS['nbreactions'] = 0;
            }
            $GLOBALS['nbreactions'] = $GLOBALS['nbreactions'] + 1;
            $idreaction = 'reaction'.$GLOBALS['nbreactions'];
        } else {
            $idreaction = URLify::slug($title);
        }

        $userReactions = null;
        if ($user = $this->wiki->getUser()) {
            $userReactions = $this->wiki->services->get(ReactionManager::class)->getReactions(
                $this->wiki->GetPageTag(),
                [$idreaction],
                $user['name'],
            );
            $uniqueId = $idreaction.'|'.$this->wiki->getPageTag();
            if (isset($userReactions[$uniqueId]['reactions'])) {
                $userReactions = array_column($userReactions[$uniqueId]['reactions'], 'id');
            }
        }
        $output = '';

        $items = $this->formatReactionItems($idreaction);

        $output .= $this->render("@core/reactions.twig", [
            'reactionId' => $idreaction,
            'title' => empty($title) ? _t('REACTION_SHARE_YOUR_REACTION') : $title,
            'connected' => $user,
            'reactionItems' => $items,
            'userName' => $user['name'] ?? '',
            'userReaction' => $userReactions,
            'maxReaction' => $maxReaction,
            'pageTag' => $this->wiki->getPageTag()
        ]);
        return $output;
    }
}
