<?php
/*
List all orphaned pages BUT bazar records
*/

use YesWiki\Tags\Service\TagsManager;

$tagsManager = $this->services->get(TagsManager::class);

if ($pages = $tagsManager->getPagesByTags('', 'wiki', '', '')) {
    foreach ($pages as $page) {
        if ($this->IsOrphanedPage($page['tag'])) {
            echo $this->ComposeLinkToPage($page['tag'], '', '', 0), "<br />\n";
        }
    }
} else {
    echo '<i>' . _t('NO_ORPHAN_PAGES') . '</i>';
}
