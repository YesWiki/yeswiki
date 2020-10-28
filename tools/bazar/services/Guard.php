<?php

namespace YesWiki\Bazar\Service;

class Guard
{
    protected $wiki;

    public function __construct($wiki)
    {
        $this->wiki = $wiki;
    }

    // Teste les droits d'acces champ par champ du contenu d'un fiche bazar
    // Si utilisateur connecte est  proprietaire ou adminstrateur : acces a tous les champs
    // Sinon ne sont retournes que les champs dont les droits d'acces sont compatibles.
    // Introduction du droit % : seul le proprietaire peut acceder
    public function checkAcls($page, $tag)
    {
        // TODO :
        // loadpagebyid
        // bazarliste ...
        // champ mot de passe ?
        //

        $INDEX_CHELOUS = ['radio', 'liste', 'checkbox', 'listefiche', 'checkboxfiche'];
        if ($this->isPageOwner($page)) {
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
                        if ($this->wiki->getMethod() == 'raw') {
                            $fieldname[] = $line[1];
                        }
                    }
                    if (isset($line[11]) && $line[11] != '') {
                        if ($this->wiki->CheckAcl($line[11]) == "%") {
                            $line[11] = $this->wiki->GetUserName();
                        }
                        if (!$this->wiki->CheckACL($line[11])) {
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

    protected function isPageOwner($page)
    {
        // check if user is logged in
        if (!$this->wiki->GetUser()) {
            return false;
        }
        // check if user is owner
        if ($page["owner"] == $this->wiki->GetUserName()) {
            return true;
        }
    }
}
