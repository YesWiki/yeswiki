<?php

use YesWiki\Security\Controller\SecurityController;

$isAdmin = $this->UserIsAdmin();

if ($isAdmin && isset($_GET['delete_tag'])) {
    if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
        throw new Exception(_t('WIKI_IN_HIBERNATION'));
    }
    $sql = 'DELETE FROM ' . $this->config['table_prefix'] . "triples WHERE property='http://outils-reseaux.org/_vocabulary/tag' and id IN (" . $this->services->get(YesWiki\Core\Service\DbService::class)->escape($_GET['delete_tag']) . ')';
    $this->Query($sql);
}

// on recupere tous les tags existants
$sql = 'SELECT id, value, resource FROM ' . $this->config['table_prefix'] . "triples WHERE property='http://outils-reseaux.org/_vocabulary/tag' ORDER BY value ASC, resource ASC";
$tab_tous_les_tags = $this->LoadAll($sql);

if (is_array($tab_tous_les_tags) && count($tab_tous_les_tags) > 0) {
    $tags = [];
    foreach ($tab_tous_les_tags as $tag) {
        $tagName = stripslashes($tag['value']);
        if (empty($tags[$tagName])) {
            $tags[$tagName] = [$tag];
        } else {
            $tags[$tagName][] = $tag;
        }
    }
    echo $this->render('@tags/admintag-action.twig', [
        'tags' => $tags,
        'isAdmin' => $isAdmin,
    ]);
} else {
    echo '<div class="alert alert-info">' . _t('TAGS_NO_TAG') . '</div>';
}
