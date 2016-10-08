<?php
public function loadPage($tag, $time = "", $cache = 1)
{
    // retrieve from cache
    if (!$time && $cache && (($cachedPage = $this->GetCachedPage($tag)) !== false)) {
        if ($cachedPage and !isset($cachedPage["metadatas"])) {
            $cachedPage["metadatas"] = $this->GetMetaDatas($tag);
        }
        $page = $cachedPage;
    } else {
        // load page
        $sql = 'SELECT * FROM ' . $this->config['table_prefix'] . 'pages' . " WHERE tag = '" . mysqli_real_escape_string($this->dblink, $tag) . "' AND " . ($time ? "time = '" . mysqli_real_escape_string($this->dblink, $time) . "'" : "latest = 'Y'") . " LIMIT 1";
        $page = $this->LoadSingle($sql);
        // si la page existe, on charge les meta-donnees
        if ($page) {
            $page["metadatas"] = $this->GetMetaDatas($tag);
        }

        $type = $this->GetTripleValue($tag, 'http://outils-reseaux.org/_vocabulary/type', '', '');
        if ($type == 'fiche_bazar') {
            $page = $this->checkBazarAcls($page, $tag);
        }
        // the database is in ISO-8859-15, it must be converted
        if (isset($page['body'])) {
            $page['body'] = _convert($page['body'], 'ISO-8859-15');
        }
        // cache result
        if (!$time) {
            $this->CachePage($page, $tag);
        }
    }
    return $page;
}

function checkBazarOwner($page, $tag)
{
    // check if user is logged in
    if (!$this->GetUser()) {
        return false;
    }
    // check if user is owner
    if ($page["owner"] == $this->GetUserName()) {
        return true;
    }
}

// Teste les droits d'acces champ par champ du contenu d'un fiche bazar
// Si utilisateur connecte est  proprietaire ou adminstrateur : acces a tous les champs
// Sinon ne sont retournes que les champs dont les droits d'acces sont compatibles.
// Introduction du droit % : seul le proprietaire peut acceder
function checkBazarAcls($page, $tag)
{
    // TODO :
    // loadpagebyid
    // bazarliste ...
    // champ mot de passe ?
    //
    if ($this->checkBazarOwner($page, $tag)) {
         // Pas de controle si proprietaire
        return $page;
    }
    if ($page) {
        $valjson = $page["body"];
        $valeur = json_decode($valjson, true);
        $valeur = array_map('utf8_decode', $valeur);
        if ($valeur) {
            $val_formulaire = baz_valeurs_formulaire($valeur['id_typeannonce']);
            $fieldname = array();
            foreach ($val_formulaire['template'] as $line) {
                if (isset($line[11]) && $line[11] != '') {
                    if ($this->CheckAcl($line[11]) == "%") {
                        $line[11] = $this->GetUserName();
                    }
                    if (!$this->CheckACL($line[11])) {
                         // On memorise les champs non autorise
                        $fieldname[] = $line[1];
                    }
                }
            }
            if (count($fieldname) > 0) {
                 //
                foreach ($fieldname as $field) {
                    $valeur[$field] = "";
                     // on vide le champ
                }
                $valeur = array_map("utf8_encode", $valeur);
                $page["body"] = json_encode($valeur);
            }
        }
    }
    return $page;
}
