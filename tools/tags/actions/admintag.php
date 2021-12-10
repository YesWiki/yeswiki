<?php
use YesWiki\Security\Controller\SecurityController;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if ($this->UserIsAdmin()) {
    if (isset($_GET['delete_tag'])) {
        if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $sql = 'DELETE FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" and id IN ('.mysqli_real_escape_string($this->dblink, $_GET['delete_tag']).')';
        $this->Query($sql);
    }

    // on recupere tous les tags existants
    $sql = 'SELECT id, value, resource FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" ORDER BY value ASC, resource ASC';
    $tab_tous_les_tags = $this->LoadAll($sql);

    if (is_array($tab_tous_les_tags) && count($tab_tous_les_tags)>0) {
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
        ]);
    } else {
        echo '<p>Aucun mot clé utilisé pour le moment</p>';
    }
} else {
    echo '<div class="alert alert-danger"><strong>'._t('TAGS_ACTION_ADMINTAGS').' :</strong>&nbsp;'._t('TAGS_ACTION_ADMINTAGS_ONLY_FOR_ADMINS').'...</div>'."\n";
}
