<?php
namespace YesWiki;

class Bazar extends \YesWiki\Wiki
{
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

            if ($GLOBALS['wiki']->services->get('bazar.fiche.manager')->isFiche($tag)) {
                $page = $this->checkBazarAcls($page, $tag);
            }

            // cache result
            if (!$time) {
                $this->CachePage($page, $tag);
            }
        }
        return $page;
    }

    public function checkBazarOwner($page)
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
    public function checkBazarAcls($page, $tag)
    {
        // TODO :
        // loadpagebyid
        // bazarliste ...
        // champ mot de passe ?
        //

        $INDEX_CHELOUS = ['radio', 'liste', 'checkbox', 'listefiche', 'checkboxfiche'];
        if ($this->checkBazarOwner($page)) {
            // Pas de controle si proprietaire
            return $page;
        }
        if ($page) {
            $valjson = $page["body"];
            $valeur = json_decode($valjson, true);

            if ($valeur) {
                $val_formulaire = baz_valeurs_formulaire($valeur['id_typeannonce']);
                $fieldname = array();
                foreach ($val_formulaire['template'] as $line) {
                    // cas des formulaires champs mails, qui ne doivent pas apparaitre en /raw
                    if ($line[0] == 'champs_mail' and !empty($line[6]) and $line[6] == 'form') {
                        if ($this->getMethod() == 'raw') {
                            $fieldname[] = $line[1];
                        }
                    }
                    if (isset($line[11]) && $line[11] != '') {
                        if ($this->CheckAcl($line[11]) == "%") {
                            $line[11] = $this->GetUserName();
                        }
                        if (!$this->CheckACL($line[11])) {
                            // on memorise les champs non autorisés
                            if (in_array($line[0], $INDEX_CHELOUS))
                                $fieldname[] = $line[0] . $line[1] . $line[6];
                            else
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
                    //$valeur = array_map("utf8_encode", $valeur);
                    $page["body"] = json_encode($valeur);
                }
            }
        }
        return $page;
    }
}
