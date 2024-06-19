<?php

use YesWiki\Core\Controller\PageController;
use YesWiki\Security\Controller\SecurityController;

// TODO
// -- case pour selectionner tout
// -- attention au cas ou la version mais aussi la page est effacee
//   (cf. handler deletepage) (et les commentaires)
// -- ne rien loguer si rien n'a ete efface
// -- idealement la derniere page affiche les resultats mais ne renettoie
//    pas les pages si elle est rechargee
// -- test pour savoir si quelque chose a bien ete efface

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
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
            '<form method="post" action="' . $despam_url . "\" name=\"selection\">\n" .
            "<fieldset>\n" .
            '<legend>' . _t('DESPAM_PAGES_SELECTION') . "</legend>\n";
        echo "<p>\n" .
        _t('DESPAM_ALL_CHANGES_FROM') . ' ' .
          "<select name=\"from\">\n" .
          '<option selected="selected" value="1">' . _t('DESPAM_FOR_ONE_HOUR') . "</option>\n" .
          '<option value="3">' . str_replace('{x}', 3, _t('DESPAM_FOR_X_HOURS')) . "</option>\n" .
          '<option value="6">' . str_replace('{x}', 6, _t('DESPAM_FOR_X_HOURS')) . "</option>\n" .
          '<option value="12">' . str_replace('{x}', 12, _t('DESPAM_FOR_X_HOURS')) . "</option>\n" .
          '<option value="24">' . str_replace('{x}', 24, _t('DESPAM_FOR_X_HOURS')) . "</option>\n" .
          '<option value="48">' . str_replace('{x}', 48, _t('DESPAM_FOR_X_HOURS')) . "</option>\n" .
          '<option value="168">' . _t('DESPAM_FOR_ONE_WEEK') . "</option>\n" .
          '<option value="336">' . _t('DESPAM_FOR_TWO_WEEKS') . "</option>\n" .
          '<option value="744">' . _t('DESPAM_FOR_ONE_MONTH') . "</option>\n" .
          "</select>\n" .
          '<button name="2" value="Valider">' . _t('DESPAM_VALIDATE') . "</button>\n" .
          "</p>\n";
        echo "</fieldset>\n" .
          "</form>\n" .
          "</div>\n\n";
    } elseif (!isset($_POST['clean'])) {
        // -- (2) Page de resultats et form. de selection des pages a effacer ----
        //
        if (isset($_POST['from']) && isset($_POST['2'])) {
            $requete =
              'select *
              from ' . $this->config['table_prefix'] . 'pages
              where
              time > date_sub(now(), interval ' . $this->services->get(\YesWiki\Core\Service\DbService::class)->escape($_POST['from']) . " hour)
              and latest = 'Y'
              order by `time` desc";
            $title =
              '<h2>' . str_replace('{x}', $_POST['from'], _t('DESPAM_CLEAN_SPAMMED_PAGES')) . "</h2>\n";
        }
        //echo $requete;
        $pagesFromSpammer = $this->LoadAll($requete);
        // Affichage des pages pour validation
        echo "<div class=\"action_erasespam\">\n";
        echo $title;
        echo '<form method="post" action="' . $despam_url . "\">\n";
        echo "<table>\n";
        foreach ($pagesFromSpammer as $i => $page) {
            $req = 'select * from ' . $this->config['table_prefix'] . "pages where tag = '"
                . mysqli_real_escape_string($this->dblink, $page['tag'])
                . "' order by time desc";
            $revisions = $this->LoadAll($req);

            echo "<tr>\n" .
              '<td>' .
              $page['tag'] . ' ' .
              '(' . $page['time'] . ') ' .
                ' par ' . $page['user'] . ' ' .
                '<a href="' . $this->Href('iframe', $page['tag'], ['time' => urlencode($page['time'])]) . '" ' .
                "title=\"Voir la fiche {$page['tag']} ({$page['time']})\" " .
                'class="btn btn-xs btn-default modalbox" ' .
                'data-size="modal-lg" ' .
                'data-iframe="1"><i class="fas fa-eye"></i></a>' .
              "</td>\n";
            echo '<td>' .
              '<input name="suppr[]" value="' . $page['tag'] . '" type="checkbox" /> [Suppr.!]' .
              "</td>\n";
            echo "<td>\n";
            echo '<p>';
            echo '_____________________________________________________________________________________________________';
            echo '</p><table>';

            foreach ($revisions as $revision) {
                // Si c'est la derniere version on saute cette iteration
                // ce n'est pas elle qu'on va vouloir restaurer...
                if (!isset($revision1)) {
                    $revision1 = '';
                    continue;
                }
                echo '<tr><td><input name=  "rev[]" value="' . $revision['id'] . '" type="checkbox" /></td><td>';
                echo str_replace(['{time}', '{user}'], [$revision['time'], $revision['user']], _t('DESPAM_RESTORE_FROM')) . ' ' .
                  '<a href="' . $this->Href('iframe', $page['tag'], ['time' => urlencode($revision['time'])]) . '" ' .
                    'title="' . _t('BAZ_SEE_ENTRY') . " {$page['tag']} ({$revision['time']})\" " .
                    'class="btn btn-xs btn-default modalbox" ' .
                    'data-size="modal-lg" ' .
                    'data-iframe="1"><i class="fas fa-eye"></i></a>' .
                  "</td></tr>\n";
            }
            echo "</table>\n";
            unset($revision1);
            echo //" . . . . ",$this->Format($page["user"]),"</p>\n",
              "</td>\n",
            "</tr>\n",
            '';
        }
        echo "</table>\n";
        echo "<p>Commentaire&nbsp;: <input class=\"form-control\" name=\"comment\" style=\"width: 80%;\" /></p>\n";
        echo "<p>\n" .
          '<input type="hidden" name="spammer" value="' . (isset($_POST['spammer']) ? $_POST['spammer'] : '') . "\" />\n" .
          "<input type=\"hidden\" name=\"clean\" value=\"yes\" />\n" .
          '<button class="btn btn-danger" value="Valider">' . _t('CLEAN') . " >></button>\n" .
          "</p>\n";
        echo "</form>\n";
        echo "</div>\n\n";
    } elseif (isset($_POST['clean'])) {
        if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        // -- (3) Nettoyage des pages et affichage de la page de resultats -------
        //
        $deletedPages = '';
        $restoredPages = '';

        // -- 3.1 Effacement ---
        // On efface chaque element du tableau suppr[]
        // Pour chaque page selectionnee
        if (!empty($_POST['suppr'])) {
            foreach ($_POST['suppr'] as $page) {
                // Effacement de la page en utilisant la méthode adéquate
                // (si DeleteOrphanedPage ne convient pas, soit on créé
                // une autre, soit on la modifie
                if ($this->services->get(PageController::class)->delete($page)) {
                    $deletedPages .= $page . ', ';
                }
            }
            $deletedPages = trim($deletedPages, ', ');
        }

        // -- 3.2 Restauration des pages sélectionnées ---
        if (!empty($_POST['rev'])) {
            //print_r($_POST["rev"]);
            foreach ($_POST['rev'] as $rev_id) {
                echo $rev_id . '<br>';
                // Selectionne la revision
                $revision = $this->LoadSingle('select * from ' . $this->config['table_prefix'] . "pages where id = '"
                  . mysqli_real_escape_string($this->dblink, $rev_id) . "' limit 1");

                // Fait de la derniere version de cette revision
                // une version archivee
                $requeteUpdate =
                  'update ' . $this->config['table_prefix'] . 'pages ' .
                  "set latest = 'N' " .
                  "where latest = 'Y' " .
                  "and tag = '" . $revision['tag'] . "' " .
                  'limit 1';
                $this->Query($requeteUpdate);
                $restoredPages .= $revision['tag'] . ', ';

                // add new revision
                $this->Query('insert into ' . $this->config['table_prefix'] . 'pages set ' .
                 "tag = '" . mysqli_real_escape_string($this->dblink, $revision['tag']) . "', " .
                 'time = now(), ' .
                 "owner = '" . mysqli_real_escape_string($this->dblink, $revision['owner']) . "', " .
                 "user = '" . mysqli_real_escape_string($this->dblink, 'despam') . "', " .
                 "latest = 'Y', " .
                 "body = '" . mysqli_real_escape_string($this->dblink, chop($revision['body'])) . "'");
            }
        }
        $restoredPages = trim($restoredPages, ', ');

        echo '<li>' . _t('DESPAM_RESTORED_PAGES') . '&nbsp;: ' .
        $restoredPages . ".</li>\n";
        echo '<li>' . _t('DESPAM_DELETED_PAGES') . '&nbsp;: ' .
        $deletedPages . ".</li>\n";

        echo "</ul>\n";
        echo '<p><a href="' . $despam_url . '">' . _t('DESPAM_BACK_TO_PREVIOUS_FORM') . " >></a></p>\n";
        echo "</div>\n\n";
    }
} else {
    echo '<div class="alert alert-danger">' . _t('DESPAM_ONLY_FOR_ADMINS') . '</div>';
}
