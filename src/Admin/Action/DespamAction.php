<?php

namespace YesWiki\Admin\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageOperationsService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;

/** `{{despam}}` -- converted from the procedural actions/despam.php by ticket 06. */
class DespamAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'despam';
    }

    public function components(): array
    {
        return [
            Component::for('despam')
                ->category(Category::Admin)
                ->label(_t('AB_management_despam_label'))
                ->icon('ban')
                ->hint(_t('AB_management_despam_hint'))
                ->previewHeight('200px')
                ->adminOnly()
                ->settings(
                    Setting::note('hint', '')
                        ->hint(_t('AB_management_despam_hint_details'))
                        ->documentedAt('https://yeswiki.net/?LutterContreLeSpam'),
                ),
        ];
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $despam_url = $this->getService(UrlFormatter::class)->href('', $this->getService(PageContext::class)->getTag());

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
                if (!isset($_POST['from']) || !isset($_POST['2'])) {
                    return;
                }
                $dbService = $this->getService(DbService::class);
                $requete = 'select * from ' . $this->getService(RuntimeConfig::class)['table_prefix'] . 'pages'
                    . ' where time > ' . $dbService->dateSubHours(intval($_POST['from']))
                    . " and latest = 'Y' order by time desc";
                $title = '<h2>' . str_replace('{x}', $_POST['from'], _t('DESPAM_CLEAN_SPAMMED_PAGES')) . "</h2>\n";

                $pagesFromSpammer = $this->getService(DbService::class)->loadAll($requete);

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
                    echo "</td>\n",
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

                $deletedPages = '';
                $restoredPages = '';

                if (!empty($_POST['suppr'])) {
                    foreach ($_POST['suppr'] as $page) {
                        if ($this->getService(PageOperationsService::class)->delete($page)) {
                            $deletedPages .= $page . ', ';
                        }
                    }
                    $deletedPages = trim($deletedPages, ', ');
                }

                if (!empty($_POST['rev'])) {
                    foreach ($_POST['rev'] as $rev_id) {
                        echo $rev_id . '<br>';

                        $dbService = $this->getService(DbService::class);
                        $revision = $this->getService(DbService::class)->loadSingle(
                            'select * from ' . $this->getService(RuntimeConfig::class)['table_prefix'] . 'pages where id = ? limit 1',
                            [$rev_id]
                        );
                        if (!is_array($revision)) {
                            continue;
                        }

                        $userCol = $dbService->quoteIdentifier('user');
                        $timeCol = $dbService->quoteIdentifier('time');
                        $pagesTable = $this->getService(RuntimeConfig::class)['table_prefix'] . 'pages';

                        $dbService->transactional(function () use ($dbService, $revision, $pagesTable, $userCol, $timeCol): void {
                            $dbService->query(
                                'UPDATE ' . $pagesTable . " SET latest = 'N' WHERE latest = 'Y' AND tag = ?",
                                [$revision['tag']]
                            );

                            $dbService->query(
                                'INSERT INTO ' . $pagesTable
                                . " (tag, {$timeCol}, owner, {$userCol}, latest, body) VALUES (?, " . $dbService->now() . ', ?, ?, ?, ?)',
                                [
                                    $revision['tag'],
                                    $revision['owner'],
                                    'despam',
                                    'Y',

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
