<?php

use YesWiki\Core\Service\HibernationService;
use YesWiki\Core\Service\TagsManager;
use YesWiki\Core\YesWikiAction;

class AdminTagAction extends YesWikiAction
{
    public function run()
    {
        $isAdmin = $this->wiki->UserIsAdmin();
        $tagsManager = $this->getService(TagsManager::class);

        if ($isAdmin && $this->getRequest()->query->has('delete_tag')) {
            if ($this->getService(HibernationService::class)->isWikiHibernated()) {
                throw new \Exception(_t('WIKI_IN_HIBERNATION'));
            }
            $tagsManager->deleteByIds(explode(',', $this->getRequest()->query->get('delete_tag')));
        }

        $rows = $tagsManager->getAllTriples();

        if (empty($rows)) {
            return '<div class="alert alert-info">' . _t('TAGS_NO_TAG') . '</div>';
        }

        $tags = [];
        foreach ($rows as $row) {
            $tagName = stripslashes($row['value']);
            $tags[$tagName][] = $row;
        }

        return $this->render('@core/admintag-action.twig', [
            'tags' => $tags,
            'isAdmin' => $isAdmin,
        ]);
    }
}
