<?php

namespace YesWiki\Tags\Service;

use YesWiki\Core\Service\TripleStore;

class TagsManager
{
    protected $wiki;
    protected $tripleStore;

    public function __construct($wiki, TripleStore $tripleStore)
    {
        $this->wiki = $wiki;
        $this->tripleStore = $tripleStore;
    }

    public function deleteAll($page)
    {
        //on recupere les anciens tags de la page courante
        $tabtagsexistants = $this->tripleStore->getAll($page, 'http://outils-reseaux.org/_vocabulary/tag', '', '');
        if (is_array($tabtagsexistants)) {
            foreach ($tabtagsexistants as $tab) {
                $this->tripleStore->delete($page, 'http://outils-reseaux.org/_vocabulary/tag', $tab['value'], '', '');
            }
        }

        return;
    }

    public function save($page, $liste_tags)
    {
        $tags = explode(',', mysqli_real_escape_string($this->wiki->dblink, _convert($liste_tags, YW_CHARSET, true)));

        //on recupere les anciens tags de la page courante
        $tabtagsexistants = $this->tripleStore->getAll($page, 'http://outils-reseaux.org/_vocabulary/tag', '', '');
        if (is_array($tabtagsexistants)) {
            foreach ($tabtagsexistants as $tab) {
                $tags_restants_a_effacer[] = $tab['value'];
            }
        }

        //on ajoute le tag s il n existe pas déjà
        foreach ($tags as $tag) {
            trim($tag);
            if ($tag != '') {
                if (!$this->tripleStore->exist($page, 'http://outils-reseaux.org/_vocabulary/tag', $tag, '', '')) {
                    $this->tripleStore->create($page, 'http://outils-reseaux.org/_vocabulary/tag', $tag, '', '');
                }
                //on supprime ce tag du tableau des tags restants a effacer
                if (isset($tags_restants_a_effacer)) {
                    unset($tags_restants_a_effacer[array_search($tag, $tags_restants_a_effacer)]);
                }
            }
        }

        //on supprime les tags restants a effacer
        if (isset($tags_restants_a_effacer)) {
            foreach ($tags_restants_a_effacer as $tag) {
                $this->tripleStore->delete($page, 'http://outils-reseaux.org/_vocabulary/tag', $tag, '', '');
            }
        }

        return;
    }

    public function getAll($page = '')
    {
        if ($page == '') {
            $sql = 'SELECT DISTINCT value FROM '.$this->wiki->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag"';
            return $this->wiki->LoadAll($sql);
        } else {
            return $this->tripleStore->getAll($this->wiki->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/tag', '', '');
        }
    }

    public function getPagesByTags($tags = '', $type = '', $nb = '', $tri = '')
    {
        if (!empty($tags)) {
            $req_from = ', '.$this->wiki->config['table_prefix'].'triples tags ';
            $tags = trim($tags);
            $tab_tags = explode(',', $tags);
            $nbdetags = count($tab_tags);
            $tags = implode(',', $tab_tags);
            $tags = '"'.str_replace(',', '","', _convert(mysqli_real_escape_string($this->wiki->dblink, addslashes($tags)), YW_CHARSET, true)).'"';
            $req = ' AND tags.value IN ('.$tags.') ';
            $req .= ' AND tags.property="http://outils-reseaux.org/_vocabulary/tag" AND tags.resource=tag ';
            $req_having = ' HAVING COUNT(tag)='.$nbdetags.' ';

            $req .= ' GROUP BY tag ';
            if ($req_having != '') {
                $req .= $req_having;
            }

            //gestion du tri de l'affichage
            if ($tri == 'alpha') {
                $req .= ' ORDER BY tag ASC ';
            } elseif ($tri == 'date') {
                $req .= ' ORDER BY time DESC ';
            }

            $requete = 'SELECT * FROM '.$this->wiki->config['table_prefix'].'pages'.$req_from." WHERE latest = 'Y' and comment_on = '' ".$req;

            return $this->wiki->LoadAll($requete);
        } else {
            // recuperation des pages wikis
            $sql = 'SELECT * FROM '.$this->wiki->GetConfigValue('table_prefix').'pages';
            if (!empty($taglist)) {
                $sql .= ', '.$this->wiki->config['table_prefix'].'triples tags';
            }
            $sql .= ' WHERE latest="Y" AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';

            if ($type == 'wiki') {
                $sql .= ' AND tag NOT IN (SELECT resource FROM '.$this->wiki->GetConfigValue('table_prefix').'triples WHERE property="http://outils-reseaux.org/_vocabulary/type") ';
            } elseif ($type == 'bazar') {
                $sql .= ' AND tag IN (SELECT resource FROM '.$this->wiki->GetConfigValue('table_prefix').'triples WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar")';
            }

            $sql .= ' ORDER BY tag ASC';

            return $this->wiki->LoadAll($sql);
        }
    }
}
