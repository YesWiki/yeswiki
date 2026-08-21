<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\PageOperationsService;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\ThemeManager;

class AdminPagesApiController extends YesWikiController
{
    private const ALLOWED_SORTS = ['tag', 'time', 'owner', 'type'];
    private const SORT_COLUMNS = ['tag' => 'p.tag', 'time' => 'p.time', 'owner' => 'p.owner', 'type' => 'page_type'];
    private const ALLOWED_PERPAGES = [50, 100, 150, 200, 500];
    private const ALLOWED_TYPES = ['all', 'pages', 'bazar', 'lists', 'special', 'comments'];
    private const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';
    private const SPECIAL_PAGES = [
        'BazaR', 'GererSite', 'GererDroits', 'GererThemes', 'GererMisesAJour',
        'GererUtilisateurs', 'GererDroitsActions', 'GererDroitsHandlers', 'TableauDeBord',
        'PageTitre', 'PageMenuHaut', 'PageRapideHaut', 'PageHeader', 'PageFooter',
        'PageCSS', 'PageMenu', 'PageColonneDroite', 'MotDePassePerdu',
        'ParametresUtilisateur', 'GererConfig', 'ActuYeswiki', 'LookWiki',
    ];

    #[Route('/api/admin/pages', methods: ['GET'], options: ['acl' => ['@admins']])]
    public function getPages(Request $request): Response
    {
        $this->denyAccessUnlessAdmin();

        $dbService = $this->getService(DbService::class);

        [$page, $perpage, $sort, $dir, $search, $type, $ownerFilter, $tagFilter, $aclFilter, $themeFilter]
            = $this->extractListParams($request);

        [$whereClause, $whereParams, $having, $havingParams] = $this->buildWhere($dbService, $search, $type, $ownerFilter, $tagFilter, $aclFilter, $themeFilter);

        $offset = ($page - 1) * $perpage;
        $sortCol = self::SORT_COLUMNS[$sort] ?? 'p.tag';
        $dirSql = $dir === 'desc' ? 'DESC' : 'ASC';

        $pT = $dbService->prefixTable('pages');
        $trT = $dbService->prefixTable('triples');
        $tagProp = self::TAG_PROPERTY;
        $typeCol = $dbService->quoteIdentifier('type');
        $entryType = PageType::ENTRY;
        $idTypeAnnonceExpr = $dbService->jsonExtract('p.body', '$.form_id');
        $tagsAggExpr = $dbService->groupConcat('tg.value');

        $sql = <<<SQL
            SELECT
                p.tag,
                p.time,
                p.owner,
                p.parent,
                p.user AS last_editor,
                p.metadata AS page_metadata,
                p.{$typeCol} AS page_type,
                MIN(CASE
                    WHEN p.{$typeCol} = '{$entryType}' THEN
                        {$idTypeAnnonceExpr}
                    ELSE NULL
                END) AS form_id,
                {$tagsAggExpr} AS page_tags
            FROM {$pT} p
            LEFT JOIN {$trT} tg ON tg.resource = p.tag AND tg.property = '{$tagProp}'
            WHERE {$whereClause}
            GROUP BY p.tag, p.time, p.owner, p.parent, p.user, p.metadata, p.{$typeCol}
            {$having}
            ORDER BY {$sortCol} {$dirSql}
            LIMIT ? OFFSET ?
        SQL;

        $rows = $dbService->loadAll($sql, [...$whereParams, ...$havingParams, $perpage, $offset]);

        $countSql = <<<SQL
            SELECT COUNT(DISTINCT p.tag) AS total
            FROM {$pT} p
            WHERE {$whereClause}
        SQL;

        $total = (int)($dbService->loadSingle($countSql, $whereParams)['total'] ?? 0);

        $totalPages = max(1, (int)ceil($total / $perpage));

        $defaultRead = $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['default_read_acl'] ?? '*';
        $defaultWrite = $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['default_write_acl'] ?? '*';
        $defaultComment = $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['default_comment_acl'] ?? '*';

        $pages = array_map(function ($r) use ($defaultRead, $defaultWrite, $defaultComment) {
            $metadata = !empty($r['page_metadata']) ? (json_decode($r['page_metadata'], true) ?? []) : [];
            $acls = $metadata['acls'] ?? [];

            return [
                'tag' => $r['tag'],
                'time' => $r['time'],
                'owner' => $r['owner'] ?? '',
                'last_editor' => $r['last_editor'] ?? '',
                'acl_read' => $acls['read'] ?? $defaultRead,
                'acl_write' => $acls['write'] ?? $defaultWrite,
                'acl_comment' => $acls['comment'] ?? $defaultComment,
                'parent' => $r['parent'] ?? '',
                'page_type' => $r['page_type'] ?? '',
                'form_id' => $r['form_id'] ?? '',
                'tags' => !empty($r['page_tags']) ? explode(',', $r['page_tags']) : [],
                'is_special' => in_array($r['tag'], self::SPECIAL_PAGES, true),
                'theme' => $metadata['theme'] ?? '',
                'squelette' => $metadata['squelette'] ?? '',
                'style' => $metadata['style'] ?? '',
                'preset' => $metadata['favorite_preset'] ?? '',
            ];
        }, $rows);

        $forms = $this->getForms();
        $themeManager = $this->getService(ThemeManager::class);

        $html = $this->render('@core/admin-content-table.twig', [
            'pages' => $pages,
            'forms' => $forms,
            'currentPage' => $page,
            'perpage' => $perpage,
            'totalPages' => $totalPages,
            'total' => $total,
            'sort' => $sort,
            'dir' => $dir,
            'search' => $search,
            'type' => $type,
            'ownerFilter' => $ownerFilter,
            'tagFilter' => $tagFilter,
            'aclFilter' => $aclFilter,
            'themeFilter' => $themeFilter,
            'themes' => array_keys($themeManager->getTemplates()),
            'defaultTheme' => $themeManager->getFavoriteTheme(),
            'defaultSquelette' => $themeManager->getFavoriteSquelette(),
            'defaultStyle' => $themeManager->getFavoriteStyle(),
            'defaultPreset' => $themeManager->getFavoritePreset(),
            'apiUrl' => $this->getService(UrlFormatter::class)->href('', 'api/admin/pages'),
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    #[Route('/api/admin/pages/bulk', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function bulkAction(Request $request): Response
    {
        $this->denyAccessUnlessAdmin();

        try {
            $this->getService(CsrfTokenChecker::class)
                ->checkToken('main', 'POST', 'csrf-token', false);
        } catch (TokenNotFoundException $e) {
            return $this->htmlResponse(
                '<div class="alert alert-danger"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#ban"/></svg> ' . htmlspecialchars($e->getMessage()) . '</div>',
                403
            );
        }

        $action = $request->request->get('bulk_action', '');
        $rawPages = $request->request->all()['pages'] ?? [];

        if (!is_array($rawPages) || empty($rawPages)) {
            return $this->htmlResponse(
                '<div class="alert alert-warning">' . _t('ACLS_NO_SELECTED_PAGE') . '</div>',
                400
            );
        }

        $pageTags = array_values(array_filter($rawPages, 'is_string'));
        $success = [];
        $errors = [];

        switch ($action) {
            case 'delete':
                $this->bulkDelete($pageTags, $success, $errors);
                break;
            case 'change-acls':
                $this->bulkChangeAcls($request, $pageTags, $success, $errors);
                break;
            case 'change-theme':
                $this->bulkChangeTheme($request, $pageTags, $success, $errors);
                break;
            default:
                return $this->htmlResponse(
                    '<div class="alert alert-danger">Unknown bulk action.</div>',
                    400
                );
        }

        $html = $this->render('@core/admin-content-bulk-result.twig', compact('action', 'success', 'errors'));

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'HX-Trigger' => 'refreshTable',
        ]);
    }

    /**
     * @param list<string> $pageTags
     * @param list<string> $success  the tags this ran through, appended to
     * @param list<string> $errors   one line per tag it could not run through, appended to
     */
    private function bulkDelete(array $pageTags, array &$success, array &$errors): void
    {
        $pageManager = $this->getService(PageManager::class);
        $pageOperationsService = $this->getService(PageOperationsService::class);
        $dbService = $this->getService(DbService::class);

        foreach ($pageTags as $tag) {
            try {
                $page = $pageManager->getOne($tag, null, false);
                if (empty($page)) {
                    $errors[] = $tag . ' (not found)';
                    continue;
                }
                $pageOperationsService->delete($tag);
                $success[] = $tag;
            } catch (\Throwable $th) {
                $errors[] = $tag . ': ' . $th->getMessage();
            }
        }
    }

    /**
     * @param list<string> $pageTags
     * @param list<string> $success  the tags this ran through, appended to
     * @param list<string> $errors   one line per tag it could not run through, appended to
     */
    private function bulkChangeAcls(Request $request, array $pageTags, array &$success, array &$errors): void
    {
        $mode = $request->request->get('acl_mode', 'replace');
        $appendAcl = ($mode === 'append');
        $newRead = (string)$request->request->get('acl_read', '');
        $newWrite = (string)$request->request->get('acl_write', '');
        $newComment = (string)$request->request->get('acl_comment', '');

        foreach ($pageTags as $tag) {
            try {
                if ($mode === 'default') {
                    $this->getService(AclService::class)->delete($tag);
                } else {
                    if (!empty($newRead)) {
                        $this->getService(AclService::class)->save($tag, 'read', $newRead, $appendAcl);
                    }
                    if (!empty($newWrite)) {
                        $this->getService(AclService::class)->save($tag, 'write', $newWrite, $appendAcl);
                    }
                    if (!empty($newComment)) {
                        $this->getService(AclService::class)->save($tag, 'comment', $this->filterCommentAcl($newComment), $appendAcl);
                    }
                }
                $success[] = $tag;
            } catch (\Throwable $th) {
                $errors[] = $tag . ': ' . $th->getMessage();
            }
        }
    }

    /**
     * @param list<string> $pageTags
     * @param list<string> $success  the tags this ran through, appended to
     * @param list<string> $errors   one line per tag it could not run through, appended to
     */
    private function bulkChangeTheme(Request $request, array $pageTags, array &$success, array &$errors): void
    {
        $pageManager = $this->getService(PageManager::class);
        $theme = (string)$request->request->get('theme', '');
        $squelette = (string)$request->request->get('squelette', '');
        $style = (string)$request->request->get('style', '');
        $preset = (string)$request->request->get('preset', '');

        $metadata = [];
        if (!empty($theme)) {
            $metadata['theme'] = $theme;
        }
        if (!empty($style)) {
            $metadata['style'] = $style . (substr($style, -4) === '.css' ? '' : '.css');
        }
        if (!empty($squelette)) {
            $metadata['squelette'] = ThemeManager::squeletteFileName($squelette);
        }
        if (!empty($preset)) {
            $metadata['favorite_preset'] = $preset . (substr($preset, -4) === '.css' ? '' : '.css');
        }
        if (!empty($request->request->get('reset_theme'))) {
            $metadata = ['theme' => null, 'style' => null, 'squelette' => null, 'favorite_preset' => null];
        }

        if (empty($metadata)) {
            return;
        }

        foreach ($pageTags as $tag) {
            try {
                $pageManager->setMetadata($tag, $metadata);
                $success[] = $tag;
            } catch (\Throwable $th) {
                $errors[] = $tag . ': ' . $th->getMessage();
            }
        }
    }

    /**
     * The list screen's query string, read into the values the query builder wants.
     *
     * @return array{int, int, string, string, string, string, string, string, string, string}
     *                                                                                         [page, perpage, sort, dir, search, type, owner, tag, acl, theme]
     */
    private function extractListParams(Request $request): array
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $pp = (int)$request->query->get('perpage', 50);
        $perpage = in_array($pp, self::ALLOWED_PERPAGES, true) ? $pp : 50;
        $sortRaw = (string)$request->query->get('sort', 'tag');
        $sort = in_array($sortRaw, self::ALLOWED_SORTS, true) ? $sortRaw : 'tag';
        $dir = $request->query->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $search = trim((string)$request->query->get('search', ''));
        $typeRaw = (string)$request->query->get('type', 'all');
        $type = (in_array($typeRaw, self::ALLOWED_TYPES, true) || (ctype_digit($typeRaw) && (int)$typeRaw > 0))
            ? $typeRaw : 'all';
        $ownerFilter = trim((string)$request->query->get('owner', ''));
        $tagFilter = trim((string)$request->query->get('tag_filter', ''));
        $aclFilter = trim((string)$request->query->get('acl_filter', ''));
        $themeFilter = trim((string)$request->query->get('theme_filter', ''));

        return [$page, $perpage, $sort, $dir, $search, $type, $ownerFilter, $tagFilter, $aclFilter, $themeFilter];
    }

    /**
     * The WHERE and HAVING clauses of the page list, and the values they bind.
     *
     * @return array{string, list<mixed>, string, list<mixed>} [where, whereParams, having, havingParams]
     */
    private function buildWhere(DbService $db, string $search, string $type, string $ownerFilter, string $tagFilter, string $aclFilter = '', string $themeFilter = ''): array
    {
        $conditions = ["p.latest = 'Y'", $type === 'comments' ? "p.parent != ''" : "p.parent = ''"];
        $params = [];
        $having = '';
        $havingParams = [];

        if ($search !== '') {
            $conditions[] = '(p.tag LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX
                . ' OR ' . $db->jsonAsText('p.body') . ' LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX . ')';
            $params[] = SqlParameters::likeContains($search);
            $params[] = SqlParameters::likeContains($search);
        }

        if ($ownerFilter !== '') {
            $conditions[] = 'p.owner = ?';
            $params[] = $ownerFilter;
        }

        $typeCol = 'p.' . $db->quoteIdentifier('type');

        switch ($type) {
            case 'pages':
                $conditions[] = "{$typeCol} NOT IN (?, ?)";
                $params[] = PageType::ENTRY;
                $params[] = PageType::LIST;
                break;
            case 'bazar':
                $conditions[] = "{$typeCol} = ?";
                $params[] = PageType::ENTRY;
                break;
            case 'comments':
                break;
            case 'lists':
                $conditions[] = "{$typeCol} = ?";
                $params[] = PageType::LIST;
                break;
            case 'special':
                $conditions[] = 'p.tag IN (' . SqlParameters::placeholders(count(self::SPECIAL_PAGES)) . ')';
                $params = [...$params, ...self::SPECIAL_PAGES];
                break;
            default:
                if (ctype_digit((string)$type) && (int)$type > 0) {
                    $conditions[] = "{$typeCol} = ?";
                    $params[] = PageType::ENTRY;

                    $conditions[] = $db->jsonExtract('p.body', '$.form_id') . ' = ?';
                    $params[] = (string)$type;
                }
                break;
        }

        if ($tagFilter !== '') {
            $having = "HAVING {$db->groupConcat('tg.value')} LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX;
            $havingParams[] = SqlParameters::likeContains($tagFilter);
        }

        [$aclCondition, $aclParams] = $this->buildAclFilterCondition($db, $aclFilter);
        if ($aclCondition !== null) {
            $conditions[] = $aclCondition;
            $params = [...$params, ...$aclParams];
        }

        if ($themeFilter !== '') {
            $themeManager = $this->getService(ThemeManager::class);

            $themeExpr = $db->jsonExtract('p.metadata', '$.theme');
            $explicitMatch = "{$themeExpr} = ?";
            $params[] = $themeFilter;
            if ($themeFilter === $themeManager->getFavoriteTheme()) {
                $noThemeStored = "(p.metadata IS NULL OR {$themeExpr} IS NULL)";
                $conditions[] = "({$explicitMatch} OR {$noThemeStored})";
            } else {
                $conditions[] = $explicitMatch;
            }
        }

        return [implode(' AND ', $conditions), $params, $having, $havingParams];
    }

    /**
     * @return array{string|null, list<mixed>} the condition and the values it binds
     */
    private function buildAclFilterCondition(DbService $db, string $aclFilter): array
    {
        if ($aclFilter === '') {
            return [null, []];
        }
        $parts = explode('|', $aclFilter, 2);
        if (count($parts) !== 2) {
            return [null, []];
        }
        [$privilege, $value] = $parts;
        if (!in_array($privilege, ['read', 'write', 'comment'], true)) {
            return [null, []];
        }

        $col = $db->jsonExtract('p.metadata', '$.acls.' . $privilege);
        $regexpOperator = $db->regexpOperator();

        $pattern = '(^|\\n|\\r)' . preg_replace('/([.+*?\\[\\]^$(){}|\\\\])/', '\\\\$1', $value) . '(\\n|\\r|$)';

        return ["({$col} {$regexpOperator} ?)", [$pattern]];
    }

    private function filterCommentAcl(string $list): string
    {
        $filtered = implode(',', array_filter(
            explode(',', $list),
            fn ($e) => $e !== '' && trim($e) !== '' && trim($e) !== '*'
        ));

        return $filtered === '' ? 'comments-closed' : $filtered;
    }

    /** @return array<int, string> form id => label, empty when the forms cannot be read */
    private function getForms(): array
    {
        try {
            return $this->getService(\YesWiki\Content\Service\FormManager::class)->getAllLabels();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function htmlResponse(string $html, int $code = 200): Response
    {
        return new Response($html, $code, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
