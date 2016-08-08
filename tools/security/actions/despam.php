<?php

// Charles Nepote 2005-2006
// Didier Loiseau 2005
// License GPL.
// Version 0.7.3 du 10/04/2006 a 23:37.

// TODO
// -- case pour selectionner tout
// -- attention au cas ou la version mais aussi la page est effacee
//   (cf. handler deletepage) (et les commentaires)
// -- ne rien loguer si rien n'a ete efface
// -- idealement la derniere page affiche les resultats mais ne renettoie
//    pas les pages si elle est rechargee
// -- test pour savoir si quelque chose a bien ete efface

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$despam_url = $this->href('', $this->GetPageTag());


// -- (1) Formulaire d'accueil de l'action -------------------------------
//
// Le formulaire est affiche si aucun spammer n'a encore été précisé ou
// si le champ a été laisse vide et validé


// Action réservée aux admins
if ($this->UserIsAdmin()) {
    if (empty($_POST['spammer']) && empty($_POST['from']) && !isset($_POST['clean'])) {
        echo "<div class=\"action_erasespam\">\n" .
            "<form method=\"post\" action=\"". $despam_url . "\" name=\"selection\">\n".
            "<fieldset>\n".
            "<legend>S&eacute;lection des pages</legend>\n";
        echo "<p>\n".
          "Toutes les modifications depuis ".
          "<select name=\"from\">\n".
          "<option selected=\"selected\" value=\"1\">depuis 1 heure</option>\n".
          "<option value=\"3\">depuis 3 heures</option>\n".
          "<option value=\"6\">depuis 6 heures</option>\n".
          "<option value=\"12\">depuis 12 heures</option>\n".
          "<option value=\"24\">depuis 24 heures</option>\n".
          "<option value=\"48\">depuis 48 heures</option>\n".
          "<option value=\"168\">depuis 1 semaine</option>\n".
          "<option value=\"336\">depuis 2 semaines</option>\n".
          "<option value=\"744\">depuis 1 mois</option>\n".
          "</select>\n".
          "<button name=\"2\" value=\"Valider\">Valider</button>\n".
          "</p>\n";
        echo "</fieldset>\n".
          "</form>\n".
          "</div>\n\n";
    } elseif (!isset($_POST['clean'])) {
    // -- (2) Page de resultats et form. de selection des pages a effacer ----
    //
        if (isset($_POST['from']) && isset($_POST['2'])) {
            $requete =
              "select *
              from ".$this->config["table_prefix"]."pages
              where
              time > date_sub(now(), interval " . addslashes($_POST['from']) . " hour)
              and latest = 'Y'
              order by `time` desc";
            $title =
              "<h2>Nettoyage des pages vandalisées depuis " .
              $_POST['from'] . " heure(s)</h2>\n";
        }
        //echo $requete;
        $pagesFromSpammer = $this->LoadAll($requete);
        // Affichage des pages pour validation
        echo "<div class=\"action_erasespam\">\n";
        echo $title;
        echo "<form method=\"post\" action=\"". $despam_url . "\">\n";
        echo "<table>\n";
        foreach ($pagesFromSpammer as $i => $page) {
            $req = "select * from ".$this->config["table_prefix"]."pages where tag = '"
                .mysqli_real_escape_string($this->dblink, $page["tag"])
                ."' order by time desc";
            $revisions = $this->LoadAll($req);

            echo "<tr>\n".
              "<td>".
              $page["tag"]. " ".
              "(". $page["time"]. ") ".
                " par ". $page['user'] . " ".
              "</td>\n";
            echo "<td>".
              "<input name=\"suppr[]\" value=\"" . $page["tag"] . "\" type=\"checkbox\" /> [Suppr.!]".
              "</td>\n";
            echo "<td>\n";
            echo "<p>";
            echo "_____________________________________________________________________________________________________";
            echo "<p>";



            foreach ($revisions as $revision) {
                // Si c'est la derniere version on saute cette iteration
                // ce n'est pas elle qu'on va vouloir restaurer...
                if (!isset($revision1)) {
                    $revision1 = "";
                    continue;
                }
                echo "<input name=  \"rev[]\" value=\"" . $revision["id"] . "\" type=\"checkbox\" /> ";
                echo "Restaurer depuis la version du ".
                   " ".$revision["time"]." ".
                  " par ". $revision['user'] . " ".
                  "<br />\n";
            }
            unset($revision1);
            echo //" . . . . ",$this->Format($page["user"]),"</p>\n",
              "</td>\n",
              "</tr>\n",
              "";
        }
        echo "</table>\n";
        echo "<p>Commentaire&nbsp;: <input class=\"form-control\" name=\"comment\" style=\"width: 80%;\" /></p>\n";
        echo "<p>\n".
          "<input type=\"hidden\" name=\"spammer\" value=\"" . (isset($_POST['spammer']) ? $_POST['spammer'] : '') . "\" />\n".
          "<input type=\"hidden\" name=\"clean\" value=\"yes\" />\n".
          "<button class=\"btn btn-danger\" value=\"Valider\">Nettoyer >></button>\n".
          "</p>\n";
        echo "</form>\n";
        echo "</div>\n\n";
    } elseif (isset($_POST['clean'])) {
    // -- (3) Nettoyage des pages et affichage de la page de resultats -------
    //
        $deletedPages = "";
        $restoredPages = "";

        // -- 3.1 Effacement ---
        // On efface chaque element du tableau suppr[]
        // Pour chaque page selectionnee
        if (!empty($_POST['suppr'])) {
            foreach ($_POST['suppr'] as $page) {
                // Effacement de la page en utilisant la méthode adéquate
                // (si DeleteOrphanedPage ne convient pas, soit on créé
                // une autre, soit on la modifie
                $this->DeleteOrphanedPage($page);
                $deletedPages .= $page . ", ";
            }
            $deletedPages = trim($deletedPages, ", ");
        }


        // -- 3.2 Restauration des pages sélectionnées ---
        if (!empty($_POST['rev'])) {
            //print_r($_POST["rev"]);
            foreach ($_POST["rev"] as $rev_id) {
                echo $rev_id."<br>";
                // Selectionne la revision
                $revision = $this->LoadSingle("select * from ".$this->config["table_prefix"]."pages where id = '"
                  .mysqli_real_escape_string($this->dblink, $rev_id)."' limit 1");


                // Fait de la derniere version de cette revision
                // une version archivee
                $requeteUpdate =
                  "update " . $this->config["table_prefix"] . "pages " .
                  "set latest = 'N' ".
                  "where latest = 'Y' " .
                  "and tag = '" . $revision["tag"] . "' " .
                  "limit 1";
                $this->Query($requeteUpdate);
                $restoredPages .= $revision["tag"] . ", ";

                 // add new revision
                $this->Query("insert into ".$this->config["table_prefix"]."pages set ".
                 "tag = '".mysqli_real_escape_string($this->dblink, $revision['tag'])."', ".
                 "time = now(), ".
                 "owner = '".mysqli_real_escape_string($this->dblink, $revision['owner'])."', ".
                 "user = '".mysqli_real_escape_string($this->dblink, "despam")."', ".
                 "latest = 'Y', ".
                 "body = '".mysqli_real_escape_string($this->dblink, chop($revision['body']))."'");
            }
        }
        $restoredPages = trim($restoredPages, ", ");

        echo "<li>Pages restaurées&nbsp;: " .
        $restoredPages . ".</li>\n";
        echo "<li>Pages supprimées&nbsp;: " .
        $deletedPages . ".</li>\n" ;

        echo "</ul>\n";
        echo "<p><a href=\"". $despam_url. "\">Retour au formulaire de départ >></a></p>\n";
        echo "</div>\n\n";
    }
} else {
    echo '<div class="alert alert-danger">Action {{despam}} réservée aux administrateurs.</div>';
}
