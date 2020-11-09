<?php

namespace YesWiki\Tags\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Wiki;

class TagsManager
{
    protected $wiki;
    protected $dbService;
    protected $tripleStore;
    protected $params;

    public function __construct(Wiki $wiki, DbService $dbService, TripleStore $tripleStore, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->tripleStore = $tripleStore;
        $this->params = $params;
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
        // TODO check if we need to escape here, or if we can do that in the tripleStore methods
        $tags = explode(',', $this->dbService->escape(_convert($liste_tags, YW_CHARSET, true)));

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
            // TODO use tripleStore service
            $sql = 'SELECT DISTINCT value FROM'.$this->dbService->prefixTable('triples').'WHERE property="http://outils-reseaux.org/_vocabulary/tag"';
            return $this->dbService->loadAll($sql);
        } else {
            return $this->tripleStore->getAll($this->wiki->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/tag', '', '');
        }
    }

    public function getPagesByTags($tags = '', $type = '', $nb = '', $tri = '')
    {
        if (!empty($tags)) {
            $req_from = ', '.$this->dbService->prefixTable('triples').$this->dbService->prefixTable('tags');
            $tags = trim($tags);
            $tab_tags = explode(',', $tags);
            $nbdetags = count($tab_tags);
            $tags = implode(',', $tab_tags);
            $tags = '"'.str_replace(',', '","', _convert($this->dbService->escape(addslashes($tags)), YW_CHARSET, true)).'"';
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

            $requete = 'SELECT * FROM '.$this->dbService->prefixTable('pages').$req_from." WHERE latest = 'Y' and comment_on = '' ".$req;

            return $this->dbService->loadAll($requete);
        } else {
            // recuperation des pages wikis
            $sql = 'SELECT * FROM '.$this->dbService->prefixTable('pages');
            if (!empty($taglist)) {
                $sql .= ', '.$this->dbService->prefixTable('triples').$this->dbService->prefixTable('tags');
            }
            $sql .= ' WHERE latest="Y" AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';

            if ($type == 'wiki') {
                $sql .= ' AND tag NOT IN (SELECT resource FROM '.$this->dbService->prefixTable('triples').'WHERE property="http://outils-reseaux.org/_vocabulary/type") ';
            } elseif ($type == 'bazar') {
                $sql .= ' AND tag IN (SELECT resource FROM '.$this->dbService->prefixTable('triples').'WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar")';
            }

            $sql .= ' ORDER BY tag ASC';

            return $this->dbService->loadAll($sql);
        }
    }
}
