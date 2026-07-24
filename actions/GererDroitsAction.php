<?php

/**
 * Cette action à pour but de gérer massivement les droits sur les pages d'un wiki.
 * Les pages s'affichent et sont modifiées en fonction du squelette qu'elles utilisent (définis par l'utilisateur).
 */

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\GroupController;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Core\Service\TemplateHelperService;
use YesWiki\Core\YesWikiAction;

class GererDroitsAction extends YesWikiAction
{
    protected $dbService;
    protected $hibernationService;
    protected $utils;
    protected $groupController;

    public function run()
    {
        // action réservée aux admins
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ACLS_RESERVED_FOR_ADMINS'),
            ]);
        }
        // get services
        $this->dbService = $this->getService(DbService::class);
        $this->hibernationService = $this->getService(HibernationService::class);
        $this->utils = $this->getService(TemplateHelperService::class);
        $this->groupController = $this->getService(GroupController::class);

        $request = $this->getRequest();
        list('success' => $success, 'error' => $error) = $this->manageChangeRights($request->request->all());
        list('filter' => $filter, 'search' => $search) = $this->getFilterAndSearch($request->query->all(), $request->request->all());

        // récupération de tous les formulaires
        $forms = $this->getService(FormManager::class)->getAll();

        // récupération de la liste des pages (ACLs live in the pages row's own metadata
        // column, not a separate acls table -- no join/subquery needed, just extract each
        // privilege straight out of this same row)
        $pagesTableName = trim($this->dbService->prefixTable('pages'));
        $aclReadExpr = $this->dbService->jsonExtract("$pagesTableName.metadata", '$.acls.read');
        $aclWriteExpr = $this->dbService->jsonExtract("$pagesTableName.metadata", '$.acls.write');
        $aclCommentExpr = $this->dbService->jsonExtract("$pagesTableName.metadata", '$.acls.comment');
        $liste_pages = $this->wiki->Query(<<<SQL
    SELECT tag,
    $aclReadExpr AS acl_read,
    $aclWriteExpr AS acl_write,
    $aclCommentExpr AS acl_comment
    FROM $pagesTableName
        WHERE latest='Y' $search
            ORDER BY $pagesTableName.tag ASC
    SQL);
        $pageEtDroits = [];
        while ($pages = $liste_pages->fetch(\PDO::FETCH_ASSOC)) {
            $pageEtDroits[] = $this->utils->recupDroits($pages);
        }

        $groups = $this->groupController->getAll();

        return $this->render(
            '@core/gerer-droits-action.twig',
            [
                'filTer' => $filter,
                'error' => $error,
                'success' => $success,
                'forms' => $forms,
                'pageEtDroits' => $pageEtDroits,
                'groups' => $groups,
                'isHibernated' => $this->hibernationService->isWikiHibernated(),
            ]
        );
    }

    /**
     * manage change of rights based on $_POST.
     *
     * @return array ['success'=>string, 'error'=>string]
     */
    protected function manageChangeRights(array $post): array
    {
        $success = '';
        $error = '';

        // Modification de droits
        if (isset($post['geredroits_modifier'])) {
            if (!isset($post['selectpage'])) {
                $error = _t('ACLS_NO_SELECTED_PAGE');
            } elseif (
                $post['typemaj'] !== 'default'
                && empty($post['newlire'])
                && empty($post['newecrire'])
                && empty($post['newcomment'])
                && empty($post['newlire_advanced'])
                && empty($post['newecrire_advanced'])
                && empty($post['newcomment_advanced'])
            ) {
                $error = _t('ACLS_NO_SELECTED_RIGHTS');
            } elseif (is_array($post['selectpage'])) {
                foreach (array_filter($post['selectpage'], 'is_string') as $page_cochee) {
                    if ($post['typemaj'] === 'default') {
                        $this->wiki->DeleteAcl($page_cochee);
                    } else {
                        $appendAcl = ($post['typemaj'] === 'ajouter');
                        if (!empty($post['newlire_advanced'])) {
                            $this->wiki->SaveAcl($page_cochee, 'read', $post['newlire_advanced'], $appendAcl);
                        } elseif (!empty($post['newlire'])) {
                            $this->wiki->SaveAcl($page_cochee, 'read', $post['newlire'], $appendAcl);
                        }
                        if (!empty($post['newecrire_advanced'])) {
                            $this->wiki->SaveAcl($page_cochee, 'write', $post['newecrire_advanced'], $appendAcl);
                        } elseif (!empty($post['newecrire'])) {
                            $this->wiki->SaveAcl($page_cochee, 'write', $post['newecrire'], $appendAcl);
                        }
                        if (!empty($post['newcomment_advanced'])) {
                            $this->wiki->SaveAcl($page_cochee, 'comment', $this->filterCommentRightsBeforeSave($post['newcomment_advanced']), $appendAcl);
                        } elseif (!empty($post['newcomment'])) {
                            $this->wiki->SaveAcl($page_cochee, 'comment', $this->filterCommentRightsBeforeSave($post['newcomment']), $appendAcl);
                        }
                    }
                }

                $success = _t('ACLS_RIGHTS_WERE_SUCCESFULLY_CHANGED');
            }
        }

        return compact(['success', 'error']);
    }

    /**
     * récupération des filtres.
     *
     * @return array ['filter'=>string,'search'=>string]
     */
    protected function getFilterAndSearch(array $get, array $post): array
    {
        $filter = $get['filter'] ?? '';
        $search = '';
        if (!empty($filter)) {
            $filter = strval($filter);
            if ($filter === 'pages') {
                $search = <<<SQL
              AND tag NOT IN (
              SELECT DISTINCT resource FROM {$this->dbService->prefixTable('triples')}
              WHERE value = 'fiche_bazar'
            )
            SQL;
            } elseif ($filter === 'specialpages') {
                $search = <<<SQL
               AND tag IN ('BazaR','GererSite','GererDroits','GererThemes','GererMisesAJour','GererUtilisateurs',
                'GererDroitsActions','GererDroitsHandlers','TableauDeBord',
                'PageTitre','PageMenuHaut','PageRapideHaut','PageHeader','PageFooter','PageCSS','PageMenu',
                'PageColonneDroite','MotDePassePerdu','ParametresUtilisateur','GererConfig','ActuYeswiki','LookWiki')
              SQL;
            } elseif ($filter === strval(intval($filter))) {
                $requete_pages_wiki_bazar_fiches = <<<SQL
              SELECT DISTINCT resource FROM {$this->dbService->prefixTable('triples')}
              WHERE value = 'fiche_bazar' AND property = 'http://outils-reseaux.org/_vocabulary/type'
              ORDER BY resource ASC
            SQL;

                $search = <<<SQL
              AND body LIKE '%"id_typeannonce":"{$this->dbService->escape($filter)}"%'
              AND tag IN ($requete_pages_wiki_bazar_fiches)
            SQL;
            } elseif ($filter === 'lists') {
                $requete_pages_wiki_listes = <<<SQL
                SELECT DISTINCT resource FROM {$this->dbService->prefixTable('triples')}
                WHERE value = 'liste' AND property = 'http://outils-reseaux.org/_vocabulary/type'
                ORDER BY resource ASC
              SQL;
                $search = <<<SQL
                AND tag IN ($requete_pages_wiki_listes)
              SQL;
            } else {
                $filter = '';
            }
        } else {
            $filter = '';
        }
        if (empty($filter) && !empty($post['filter']) && is_scalar($post['filter'])) {
            $filter = strval($post['filter']);
        }

        return compact(['filter', 'search']);
    }

    protected function filterCommentRightsBeforeSave($list): string
    {
        if (empty($list) || !is_string($list)) {
            $list = '';
        } else {
            $list = implode(',', array_filter(explode(',', $list), function ($el) {
                return !empty($el) && !empty(trim($el)) && trim($el) != '*';
            }));
        }

        return empty($list) ? 'comments-closed' : $list;
    }
}
