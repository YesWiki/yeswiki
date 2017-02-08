<?php

function DeleteAllTags($page)
{
    //on recupere les anciens tags de la page courante
    $tabtagsexistants = $this->GetAllTriplesValues($page, 'http://outils-reseaux.org/_vocabulary/tag', '', '');
    if (is_array($tabtagsexistants)) {
        foreach ($tabtagsexistants as $tab) {
            $this->DeleteTriple($page, 'http://outils-reseaux.org/_vocabulary/tag', $tab['value'], '', '');
        }
    }

    return;
}

function SaveTags($page, $liste_tags)
{
    $tags = explode(',', mysqli_real_escape_string($this->dblink, _convert($liste_tags, YW_CHARSET, true)));

    //on recupere les anciens tags de la page courante
    $tabtagsexistants = $this->GetAllTriplesValues($page, 'http://outils-reseaux.org/_vocabulary/tag', '', '');
    if (is_array($tabtagsexistants)) {
        foreach ($tabtagsexistants as $tab) {
            $tags_restants_a_effacer[] = $tab['value'];
        }
    }

    //on ajoute le tag s il n existe pas déjà
    foreach ($tags as $tag) {
        trim($tag);
        if ($tag != '') {
            if (!$this->TripleExists($page, 'http://outils-reseaux.org/_vocabulary/tag', $tag, '', '')) {
                $this->InsertTriple($page, 'http://outils-reseaux.org/_vocabulary/tag', $tag, '', '');
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
            $this->DeleteTriple($page, 'http://outils-reseaux.org/_vocabulary/tag', $tag, '', '');
        }
    }

    return;
}

function GetAllTags($page = '')
{
    if ($page == '') {
        $sql = 'SELECT DISTINCT value FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag"';

        return $this->LoadAll($sql);
    } else {
        return $this->GetAllTriplesValues($this->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/tag', '', '');
    }
}

function PageList($tags = '', $type = '', $nb = '', $tri = '')
{
    if (!empty($tags)) {
        $req_from = ', '.$this->config['table_prefix'].'triples tags ';
        $tags = trim($tags);
        $tab_tags = explode(',', $tags);
        $nbdetags = count($tab_tags);
        $tags = implode(',', $tab_tags);
        $tags = '"'.str_replace(',', '","', _convert(mysqli_real_escape_string($this->dblink, addslashes($tags)), YW_CHARSET, true)).'"';
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

        $requete = 'SELECT * FROM '.$this->config['table_prefix'].'pages'.$req_from." WHERE latest = 'Y' and comment_on = '' ".$req;

        return $this->LoadAll($requete);
    } else {
        // recuperation des pages wikis
        $sql = 'SELECT * FROM '.$this->GetConfigValue('table_prefix').'pages';
        if (!empty($taglist)) {
            $sql .= ', '.$this->config['table_prefix'].'triples tags';
        }
        $sql .= ' WHERE latest="Y" AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';

        if ($type == 'wiki') {
            $sql .= ' AND tag NOT IN (SELECT resource FROM '.$this->GetConfigValue('table_prefix').'triples WHERE property="http://outils-reseaux.org/_vocabulary/type") ';
        } elseif ($type == 'bazar') {
            $sql .= ' AND tag IN (SELECT resource FROM '.$this->GetConfigValue('table_prefix').'triples WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar")';
        }

        $sql .= ' ORDER BY tag ASC';

        return $this->LoadAll($sql);
    }
}
