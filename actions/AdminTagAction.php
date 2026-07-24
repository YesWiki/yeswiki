<?php

use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\TagsManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Security\Controller\SecurityController;

class AdminTagAction extends YesWikiAction
{
    public function run()
    {
        $isAdmin = $this->wiki->UserIsAdmin();
        $dbService = $this->getService(DbService::class);

        if ($isAdmin && $this->getRequest()->query->has('delete_tag')) {
            if ($this->getService(SecurityController::class)->isWikiHibernated()) {
                throw new \Exception(_t('WIKI_IN_HIBERNATION'));
            }
            // triples.id is always an integer -- casting each token is both the fix (the
            // previous escape()-the-whole-comma-list version wrapped a "3,5,7" list into
            // one single-quoted string, so `id IN ('3,5,7')` never matched more than one
            // row) and simpler than escaping, since these are never free-text
            $ids = array_map('intval', explode(',', $this->getRequest()->query->get('delete_tag')));
            $ids = array_filter($ids);
            if (!empty($ids)) {
                $dbService->query(
                    'DELETE FROM ' . $dbService->prefixTable('triples')
                    . " WHERE property='" . TagsManager::TAG_PROPERTY . "' AND id IN (" . implode(',', $ids) . ')'
                );
            }
        }

        // on recupere tous les tags existants
        $rows = $dbService->loadAll(
            'SELECT id, value, resource FROM ' . $dbService->prefixTable('triples')
            . " WHERE property='" . TagsManager::TAG_PROPERTY . "' ORDER BY value ASC, resource ASC"
        );

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
