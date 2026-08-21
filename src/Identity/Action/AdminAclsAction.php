<?php

namespace YesWiki\Identity\Action;

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Render\Service\TemplateHelperService;

class AdminAclsAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{adminacls}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'adminacls';
    }

    public function components(): array
    {
        return [
            Component::for('adminacls')
                ->category(Category::Admin)
                ->label(_t('AB_management_gererdroits_label'))
                ->icon('key')
                ->previewHeight('200px')
                ->adminOnly(),
        ];
    }

    protected DbService $dbService;
    protected HibernationService $hibernationService;
    protected TemplateHelperService $utils;
    protected GroupOperationsService $groupOperationsService;

    public function run(): string
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ACLS_RESERVED_FOR_ADMINS'),
            ]);
        }

        $this->dbService = $this->getService(DbService::class);
        $this->hibernationService = $this->getService(HibernationService::class);
        $this->utils = $this->getService(TemplateHelperService::class);
        $this->groupOperationsService = $this->getService(GroupOperationsService::class);

        $request = $this->getRequest();
        list('success' => $success, 'error' => $error) = $this->manageChangeRights($request->request->all());
        list('filter' => $filter, 'search' => $search, 'searchParams' => $searchParams) = $this->getFilterAndSearch($request->query->all(), $request->request->all());

        $forms = $this->getService(FormManager::class)->getAll();

        $pagesTableName = trim($this->dbService->prefixTable('pages'));
        $aclReadExpr = $this->dbService->jsonExtract("$pagesTableName.metadata", '$.acls.read');
        $aclWriteExpr = $this->dbService->jsonExtract("$pagesTableName.metadata", '$.acls.write');
        $aclCommentExpr = $this->dbService->jsonExtract("$pagesTableName.metadata", '$.acls.comment');
        $liste_pages = $this->getService(DbService::class)->query(<<<SQL
    SELECT tag,
    $aclReadExpr AS acl_read,
    $aclWriteExpr AS acl_write,
    $aclCommentExpr AS acl_comment
    FROM $pagesTableName
        WHERE latest='Y' $search
            ORDER BY $pagesTableName.tag ASC
    SQL, $searchParams);
        $pageEtDroits = [];
        while ($pages = $liste_pages->fetch(\PDO::FETCH_ASSOC)) {
            $pageEtDroits[] = $this->utils->recupDroits($pages);
        }

        $groups = $this->groupOperationsService->getAll();

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
     * @param array<string, mixed> $post
     *
     * @return array{success: string, error: string}
     */
    protected function manageChangeRights(array $post): array
    {
        $success = '';
        $error = '';

        if (isset($post['geredroits_modifier'])) {
            if (!isset($post['selectpage'])) {
                $error = _t('ACLS_NO_SELECTED_PAGE');
            } elseif (
                $post['updatetype'] !== 'default'
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
                    if ($post['updatetype'] === 'default') {
                        $this->getService(AclService::class)->delete($page_cochee);
                    } else {
                        $appendAcl = ($post['updatetype'] === 'ajouter');
                        if (!empty($post['newlire_advanced'])) {
                            $this->getService(AclService::class)->save($page_cochee, 'read', $post['newlire_advanced'], $appendAcl);
                        } elseif (!empty($post['newlire'])) {
                            $this->getService(AclService::class)->save($page_cochee, 'read', $post['newlire'], $appendAcl);
                        }
                        if (!empty($post['newecrire_advanced'])) {
                            $this->getService(AclService::class)->save($page_cochee, 'write', $post['newecrire_advanced'], $appendAcl);
                        } elseif (!empty($post['newecrire'])) {
                            $this->getService(AclService::class)->save($page_cochee, 'write', $post['newecrire'], $appendAcl);
                        }
                        if (!empty($post['newcomment_advanced'])) {
                            $this->getService(AclService::class)->save($page_cochee, 'comment', $this->filterCommentRightsBeforeSave($post['newcomment_advanced']), $appendAcl);
                        } elseif (!empty($post['newcomment'])) {
                            $this->getService(AclService::class)->save($page_cochee, 'comment', $this->filterCommentRightsBeforeSave($post['newcomment']), $appendAcl);
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
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     *
     * @return array{filter: string, search: string, searchParams: list<mixed>}
     */
    protected function getFilterAndSearch(array $get, array $post): array
    {
        $filter = $get['filter'] ?? '';
        $search = '';

        $searchParams = [];
        if (!empty($filter)) {
            $filter = strval($filter);
            $typeCol = $this->dbService->quoteIdentifier('type');
            if ($filter === 'pages') {
                $search = " AND {$typeCol} <> '" . PageType::ENTRY . "'";
            } elseif ($filter === 'specialpages') {
                $search = <<<SQL
               AND tag IN ('BazaR','GererSite','GererDroits','GererThemes','GererMisesAJour','GererUtilisateurs',
                'GererDroitsActions','GererDroitsHandlers','TableauDeBord',
                'PageTitre','PageMenuHaut','PageRapideHaut','PageHeader','PageFooter','PageCSS','PageMenu',
                'PageColonneDroite','MotDePassePerdu','ParametresUtilisateur','GererConfig','ActuYeswiki','LookWiki')
              SQL;
            } elseif ($filter === strval(intval($filter))) {
                $search = ' AND ' . $this->dbService->jsonExtract('body', '$.form_id') . ' = ?'
                    . " AND {$typeCol} = '" . PageType::ENTRY . "'";
                $searchParams[] = $filter;
            } elseif ($filter === 'lists') {
                $search = " AND {$typeCol} = '" . PageType::LIST . "'";
            } else {
                $filter = '';
            }
        } else {
            $filter = '';
        }
        if (empty($filter) && !empty($post['filter']) && is_scalar($post['filter'])) {
            $filter = strval($post['filter']);
        }

        return compact(['filter', 'search', 'searchParams']);
    }

    /** @param mixed $list the submitted comment-rights list */
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
