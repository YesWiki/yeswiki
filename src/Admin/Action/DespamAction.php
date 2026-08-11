<?php

namespace YesWiki\Admin\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageOperationsService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;

/**
 * `{{despam}}` -- converted from the procedural actions/despam.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class DespamAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'despam';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        // TODO
        // -- case pour selectionner tout
        // -- attention au cas ou la version mais aussi la page est effacee
        //   (cf. handler deletepage) (et les commentaires)
        // -- ne rien loguer si rien n'a ete efface
        // -- idealement la derniere page affiche les resultats mais ne renettoie
        //    pas les pages si elle est rechargee
        // -- test pour savoir si quelque chose a bien ete efface

        $despam_url = $this->getService(UrlFormatter::class)->href('', $this->getService(PageContext::class)->getTag());

        // -- (1) Formulaire d'accueil de l'action -------------------------------
        //
        // Le formulaire est affiche si aucun spammer n'a encore été précisé ou
        // si le champ a été laisse vide et validé

        // Action réservée aux admins
        if ($this->getService(AclService::class)->isAdmin()) {
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
                    $dbService = $this->getService(DbService::class);
                    $requete =
                'select *
                      from ' . $this->getService(RuntimeConfig::class)['table_prefix'] . 'pages
                      where
                      time > ' . $dbService->dateSubHours(intval($_POST['from'])) . "
                      and latest = 'Y'
                      order by time desc";
                    $title =
                '<h2>' . str_replace('{x}', $_POST['from'], _t('DESPAM_CLEAN_SPAMMED_PAGES')) . "</h2>\n";
                }
                // echo $requete;
                $pagesFromSpammer = $this->getService(DbService::class)->loadAll($requete);
                // Affichage des pages pour validation
                echo "<div class=\"action_erasespam\">\n";
                echo $title;
                echo '<form method="post" action="' . $despam_url . "\">\n";
                echo "<table>\n";
                foreach ($pagesFromSpammer as $i => $page) {
                    $timeCol = $this->getService(DbService::class)->quoteIdentifier('time');
                    $req = 'select * from ' . $this->getService(RuntimeConfig::class)['table_prefix']
                . 'pages where tag = ? order by ' . $timeCol . ' desc';
                    $revisions = $this->getService(DbService::class)->loadAll($req, [$page['tag']]);

                    echo "<tr>\n" .
                '<td>' .
                $page['tag'] . ' ' .
                '(' . $page['time'] . ') ' .
                ' par ' . $page['user'] . ' ' .
                '<a href="' . $this->getService(UrlFormatter::class)->href('iframe', $page['tag'], ['time' => urlencode($page['time'])]) . '" ' .
                "title=\"Voir la fiche {$page['tag']} ({$page['time']})\" " .
                'class="btn btn-xs btn-default modalbox" ' .
                'data-size="modal-lg" ' .
                'data-iframe="1"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#eye"/></svg></a>' .
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
                  '<a href="' . $this->getService(UrlFormatter::class)->href('iframe', $page['tag'], ['time' => urlencode($revision['time'])]) . '" ' .
                  'title="' . _t('BAZ_SEE_ENTRY') . " {$page['tag']} ({$revision['time']})\" " .
                  'class="btn btn-xs btn-default modalbox" ' .
                  'data-size="modal-lg" ' .
                  'data-iframe="1"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#eye"/></svg></a>' .
                  "</td></tr>\n";
                    }
                    echo "</table>\n";
                    unset($revision1);
                    echo // " . . . . ",$this->getService(MarkdownFormatterService::class)->format($page["user"]),"</p>\n",
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
                if ($this->getService(HibernationService::class)->isWikiHibernated()) {
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
                        if ($this->getService(PageOperationsService::class)->delete($page)) {
                            $deletedPages .= $page . ', ';
                        }
                    }
                    $deletedPages = trim($deletedPages, ', ');
                }

                // -- 3.2 Restauration des pages sélectionnées ---
                if (!empty($_POST['rev'])) {
                    // print_r($_POST["rev"]);
                    foreach ($_POST['rev'] as $rev_id) {
                        echo $rev_id . '<br>';
                        // Selectionne la revision
                        $dbService = $this->getService(DbService::class);
                        $revision = $this->getService(DbService::class)->loadSingle(
                            'select * from ' . $this->getService(RuntimeConfig::class)['table_prefix'] . 'pages where id = ? limit 1',
                            [$rev_id]
                        );
                        if (!is_array($revision)) {
                            continue;
                        }

                        // Demote the current revision and promote the chosen one, atomically:
                        // the same demote-then-insert pair PageManager::save() runs, so the same
                        // hazard -- a failure in between leaves the page with no `latest = 'Y'`
                        // row and it vanishes. One transaction per restored revision, so a
                        // failure on the fifth keeps the four already restored.
                        $userCol = $dbService->quoteIdentifier('user');
                        $timeCol = $dbService->quoteIdentifier('time');
                        $pagesTable = $this->getService(RuntimeConfig::class)['table_prefix'] . 'pages';

                        $dbService->transactional(function () use ($dbService, $revision, $pagesTable, $userCol, $timeCol): void {
                            $dbService->query(
                                'UPDATE ' . $pagesTable . " SET latest = 'N' WHERE latest = 'Y' AND tag = ?",
                                [$revision['tag']]
                            );
                            // `time` takes the driver's own now() expression, so it is the one
                            // slot in the VALUES list that is not a placeholder
                            $dbService->query(
                                'INSERT INTO ' . $pagesTable
                                . " (tag, {$timeCol}, owner, {$userCol}, latest, body) VALUES (?, " . $dbService->now() . ', ?, ?, ?, ?)',
                                [
                                    $revision['tag'],
                                    $revision['owner'],
                                    'despam',
                                    'Y',
                                    // the revision is a raw row, so its body is re-encoded rather
                                    // than copied verbatim: a row left in the legacy shape lands
                                    // in the new one
                                    PageBody::encode(PageBody::decode($revision['body'])),
                                ]
                            );
                        });

                        $restoredPages .= $revision['tag'] . ', ';
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
    }
}
