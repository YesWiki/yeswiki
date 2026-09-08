<?php

use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Security\Controller\SecurityController;

$isAdmin = $this->UserIsAdmin();

if ($isAdmin && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['delete_tag'])) {
    if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
        throw new Exception(_t('WIKI_IN_HIBERNATION'));
    }
    try {
        $this->services->get(CsrfTokenController::class)->checkToken('main', 'POST', 'csrf-token', false);
        $ids = array_filter(
            array_map('intval', explode(',', strval($_POST['delete_tag']))),
            function ($id) {
                return $id > 0;
            }
        );
        if (!empty($ids)) {
            $sql = 'DELETE FROM ' . $this->config['table_prefix'] . 'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" and id IN (' . implode(',', $ids) . ')';
            $this->Query($sql);
        }
    } catch (Throwable $th) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($th->getMessage(), ENT_QUOTES, YW_CHARSET) . '</div>' . "\n";
    }
}

// on recupere tous les tags existants
$aclFilter = $this->services->get(YesWiki\Core\Service\AclService::class)->readableFilterSql('resource', true);
$sql = 'SELECT id, value, resource FROM ' . $this->config['table_prefix'] . 'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag"' . $aclFilter . ' ORDER BY value ASC, resource ASC';
$tab_tous_les_tags = $this->LoadAll($sql);

if (is_array($tab_tous_les_tags) && count($tab_tous_les_tags) > 0) {
    $tags = [];
    foreach ($tab_tous_les_tags as $tag) {
        $tagName = _convert(stripslashes($tag['value']), 'ISO-8859-1');
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
