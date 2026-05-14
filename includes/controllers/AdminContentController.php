<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\ThemeManager;
use YesWiki\Core\YesWikiController;

class AdminContentController extends YesWikiController
{
    private const ALLOWED_SORTS    = ['tag', 'time', 'owner', 'type'];
    private const SORT_COLUMNS     = ['tag' => 'p.tag', 'time' => 'p.time', 'owner' => 'p.owner', 'type' => 'tp.value'];
    private const ALLOWED_PERPAGES = [50, 100, 150, 200, 500];
    private const ALLOWED_TYPES    = ['all', 'pages', 'bazar', 'lists', 'special', 'comments'];
    private const TAG_PROPERTY     = 'http://outils-reseaux.org/_vocabulary/tag';
    private const TYPE_PROPERTY    = 'http://outils-reseaux.org/_vocabulary/type';

    // -------------------------------------------------------------------------
    // GET /api/admin/pages  –  returns an HTML fragment (table + pagination)
    // -------------------------------------------------------------------------

    /**
     * @Route("/api/admin/pages", methods={"GET"}, options={"acl":{"@admins"}})
     */
    public function getPages(Request $request): Response
    {
        $this->denyAccessUnlessAdmin();

        $dbService = $this->getService(DbService::class);

        [$page, $perpage, $sort, $dir, $search, $type, $ownerFilter, $tagFilter, $aclFilter]
            = $this->extractListParams($request);

        [$whereClause, $having] = $this->buildWhere($dbService, $search, $type, $ownerFilter, $tagFilter, $aclFilter);

        $offset    = ($page - 1) * $perpage;
        $sortCol   = self::SORT_COLUMNS[$sort] ?? 'p.tag';
        $dirSql    = $dir === 'desc' ? 'DESC' : 'ASC';

        $pT       = $dbService->prefixTable('pages');
        $aT       = $dbService->prefixTable('acls');
        $trT      = $dbService->prefixTable('triples');
        $tagProp  = self::TAG_PROPERTY;
        $typeProp = self::TYPE_PROPERTY;

        $sql = <<<SQL
            SELECT
                p.tag,
                p.time,
                p.owner,
                p.comment_on,
                p.user AS last_editor,
                a_r.list AS acl_read,
                a_w.list AS acl_write,
                a_c.list AS acl_comment,
                tp.value AS page_type,
                MIN(CASE
                    WHEN tp.value = 'fiche_bazar' THEN
                        SUBSTRING_INDEX(SUBSTRING(p.body, LOCATE('"id_typeannonce":"', p.body) + 18), '"', 1)
                    ELSE NULL
                END) AS form_id,
                GROUP_CONCAT(DISTINCT tg.value ORDER BY tg.value SEPARATOR ',') AS page_tags
            FROM {$pT} p
            LEFT JOIN {$aT} a_r ON a_r.page_tag = p.tag AND a_r.privilege = 'read'
            LEFT JOIN {$aT} a_w ON a_w.page_tag = p.tag AND a_w.privilege = 'write'
            LEFT JOIN {$aT} a_c ON a_c.page_tag = p.tag AND a_c.privilege = 'comment'
            LEFT JOIN {$trT} tg ON tg.resource = p.tag AND tg.property = '{$tagProp}'
            LEFT JOIN {$trT} tp ON tp.resource = p.tag AND tp.property = '{$typeProp}'
            WHERE {$whereClause}
            GROUP BY p.tag, p.time, p.owner, p.comment_on, p.user, a_r.list, a_w.list, a_c.list, tp.value
            {$having}
            ORDER BY {$sortCol} {$dirSql}
            LIMIT {$perpage} OFFSET {$offset}
        SQL;

        $rows  = $dbService->loadAll($sql) ?? [];

        // Count query – includes ACL joins so aclFilter conditions work
        $countSql = <<<SQL
            SELECT COUNT(DISTINCT p.tag) AS total
            FROM {$pT} p
            LEFT JOIN {$aT} a_r ON a_r.page_tag = p.tag AND a_r.privilege = 'read'
            LEFT JOIN {$aT} a_w ON a_w.page_tag = p.tag AND a_w.privilege = 'write'
            LEFT JOIN {$aT} a_c ON a_c.page_tag = p.tag AND a_c.privilege = 'comment'
            WHERE {$whereClause}
        SQL;
        $total = (int) ($dbService->loadSingle($countSql)['total'] ?? 0);

        $totalPages = max(1, (int) ceil($total / $perpage));

        $defaultRead    = $this->wiki->config['default_read_acl']    ?? '*';
        $defaultWrite   = $this->wiki->config['default_write_acl']   ?? '*';
        $defaultComment = $this->wiki->config['default_comment_acl'] ?? '*';

        $pages = array_map(function ($r) use ($defaultRead, $defaultWrite, $defaultComment) {
            return [
                'tag'        => $r['tag'],
                'time'       => $r['time'],
                'owner'      => $r['owner'] ?? '',
                'last_editor'=> $r['last_editor'] ?? '',
                'acl_read'   => $r['acl_read']    ?? $defaultRead,
                'acl_write'  => $r['acl_write']   ?? $defaultWrite,
                'acl_comment'=> $r['acl_comment'] ?? $defaultComment,
                'comment_on' => $r['comment_on']  ?? '',
                'page_type'  => $r['page_type']   ?? '',
                'form_id'    => $r['form_id']      ?? '',
                'tags'       => !empty($r['page_tags']) ? explode(',', $r['page_tags']) : [],
            ];
        }, $rows);

        $forms = $this->getForms();

        $html = $this->render('@core/admin-content-table.twig', [
            'pages'       => $pages,
            'forms'       => $forms,
            'currentPage' => $page,
            'perpage'     => $perpage,
            'totalPages'  => $totalPages,
            'total'       => $total,
            'sort'        => $sort,
            'dir'         => $dir,
            'search'      => $search,
            'type'        => $type,
            'ownerFilter' => $ownerFilter,
            'tagFilter'   => $tagFilter,
            'aclFilter'   => $aclFilter,
            'apiUrl'      => $this->wiki->Href('', 'api/admin/pages'),
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    // -------------------------------------------------------------------------
    // POST /api/admin/pages/bulk  –  execute a bulk operation
    // -------------------------------------------------------------------------

    /**
     * @Route("/api/admin/pages/bulk", methods={"POST"}, options={"acl":{"@admins"}})
     */
    public function bulkAction(Request $request): Response
    {
        $this->denyAccessUnlessAdmin();

        // CSRF check
        try {
            $this->getService(CsrfTokenController::class)
                ->checkToken('main', 'POST', 'csrf-token', false);
        } catch (TokenNotFoundException $e) {
            return $this->htmlResponse(
                '<div class="alert alert-danger"><i class="fa fa-ban"></i> ' . htmlspecialchars($e->getMessage()) . '</div>',
                403
            );
        }

        $action   = $request->request->get('bulk_action', '');
        $rawPages = $request->request->all()['pages'] ?? [];

        if (!is_array($rawPages) || empty($rawPages)) {
            return $this->htmlResponse(
                '<div class="alert alert-warning">' . _t('ACLS_NO_SELECTED_PAGE') . '</div>',
                400
            );
        }

        $pageTags = array_values(array_filter($rawPages, 'is_string'));
        $success  = [];
        $errors   = [];

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

        // Tell HTMX on the client to also refresh the table
        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'HX-Trigger'   => 'refreshTable',
        ]);
    }

    // -------------------------------------------------------------------------
    // Bulk helpers
    // -------------------------------------------------------------------------

    private function bulkDelete(array $pageTags, array &$success, array &$errors): void
    {
        $pageManager    = $this->getService(PageManager::class);
        $pageController = $this->getService(PageController::class);
        $dbService      = $this->getService(DbService::class);

        foreach ($pageTags as $tag) {
            try {
                $page = $pageManager->getOne($tag, null, false);
                if (empty($page)) {
                    $errors[] = $tag . ' (not found)';
                    continue;
                }
                if (!$pageManager->isOrphaned($tag)) {
                    $dbService->query(
                        "DELETE FROM {$dbService->prefixTable('links')}"
                        . " WHERE to_tag = '" . $dbService->escape($tag) . "'"
                    );
                }
                $pageController->delete($tag);
                $success[] = $tag;
            } catch (\Throwable $th) {
                $errors[] = $tag . ': ' . $th->getMessage();
            }
        }
    }

    private function bulkChangeAcls(Request $request, array $pageTags, array &$success, array &$errors): void
    {
        $mode      = $request->request->get('acl_mode', 'replace');
        $appendAcl = ($mode === 'append');
        $newRead    = $request->request->get('acl_read', '');
        $newWrite   = $request->request->get('acl_write', '');
        $newComment = $request->request->get('acl_comment', '');

        foreach ($pageTags as $tag) {
            try {
                if ($mode === 'default') {
                    $this->wiki->DeleteAcl($tag);
                } else {
                    if (!empty($newRead)) {
                        $this->wiki->SaveAcl($tag, 'read', $newRead, $appendAcl);
                    }
                    if (!empty($newWrite)) {
                        $this->wiki->SaveAcl($tag, 'write', $newWrite, $appendAcl);
                    }
                    if (!empty($newComment)) {
                        $this->wiki->SaveAcl($tag, 'comment', $this->filterCommentAcl($newComment), $appendAcl);
                    }
                }
                $success[] = $tag;
            } catch (\Throwable $th) {
                $errors[] = $tag . ': ' . $th->getMessage();
            }
        }
    }

    private function bulkChangeTheme(Request $request, array $pageTags, array &$success, array &$errors): void
    {
        $pageManager = $this->getService(PageManager::class);
        $theme       = $request->request->get('theme', '');
        $style       = $request->request->get('style', '');
        $squelette   = $request->request->get('squelette', '');
        $preset      = $request->request->get('preset', '');

        $metadata = [];
        if (!empty($theme)) {
            $metadata['theme'] = $theme;
        }
        if (!empty($style)) {
            $metadata['style'] = $style . (substr($style, -4) === '.css' ? '' : '.css');
        }
        if (!empty($squelette)) {
            $metadata['squelette'] = $squelette . (substr($squelette, -9) === '.tpl.html' ? '' : '.tpl.html');
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

    // -------------------------------------------------------------------------
    // Query builders
    // -------------------------------------------------------------------------

    private function extractListParams(Request $request): array
    {
        $page   = max(1, (int) $request->query->get('page', 1));
        $pp     = (int) $request->query->get('perpage', 50);
        $perpage = in_array($pp, self::ALLOWED_PERPAGES, true) ? $pp : 50;
        $sortRaw = $request->query->get('sort', 'tag');
        $sort    = in_array($sortRaw, self::ALLOWED_SORTS, true) ? $sortRaw : 'tag';
        $dir     = $request->query->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $search  = trim((string) $request->query->get('search', ''));
        $typeRaw = $request->query->get('type', 'all');
        $type    = (in_array($typeRaw, self::ALLOWED_TYPES, true) || (ctype_digit((string) $typeRaw) && (int) $typeRaw > 0))
            ? $typeRaw : 'all';
        $ownerFilter = trim((string) $request->query->get('owner', ''));
        $tagFilter   = trim((string) $request->query->get('tag_filter', ''));
        $aclFilter   = trim((string) $request->query->get('acl_filter', ''));

        return [$page, $perpage, $sort, $dir, $search, $type, $ownerFilter, $tagFilter, $aclFilter];
    }

    private function buildWhere(DbService $db, string $search, string $type, string $ownerFilter, string $tagFilter, string $aclFilter = ''): array
    {
        $conditions = ["p.latest = 'Y'", $type === 'comments' ? "p.comment_on != ''" : "p.comment_on = ''"];
        $having = '';

        if ($search !== '') {
            $escaped      = $db->escape($search);
            $conditions[] = "p.tag LIKE '%{$escaped}%'";
        }

        if ($ownerFilter !== '') {
            $escaped      = $db->escape($ownerFilter);
            $conditions[] = "p.owner = '{$escaped}'";
        }

        $trT      = $db->prefixTable('triples');
        $typeProp = self::TYPE_PROPERTY;

        switch ($type) {
            case 'pages':
                $conditions[] = "p.tag NOT IN (SELECT DISTINCT resource FROM {$trT} WHERE value = 'fiche_bazar' AND property = '{$typeProp}')";
                $conditions[] = "p.tag NOT IN (SELECT DISTINCT resource FROM {$trT} WHERE value = 'liste' AND property = '{$typeProp}')";
                break;
            case 'bazar':
                $conditions[] = "p.tag IN (SELECT DISTINCT resource FROM {$trT} WHERE value = 'fiche_bazar' AND property = '{$typeProp}')";
                break;
            case 'comments':
                // base condition already set to comment_on != ''
                break;
            case 'lists':
                $conditions[] = "p.tag IN (SELECT DISTINCT resource FROM {$trT} WHERE value = 'liste' AND property = '{$typeProp}')";
                break;
            case 'special':
                $sp = implode("','", [
                    'BazaR','GererSite','GererDroits','GererThemes','GererMisesAJour',
                    'GererUtilisateurs','GererDroitsActions','GererDroitsHandlers','TableauDeBord',
                    'PageTitre','PageMenuHaut','PageRapideHaut','PageHeader','PageFooter',
                    'PageCSS','PageMenu','PageColonneDroite','MotDePassePerdu',
                    'ParametresUtilisateur','GererConfig','ActuYeswiki','LookWiki',
                ]);
                $conditions[] = "p.tag IN ('{$sp}')";
                break;
            default:
                if (ctype_digit((string) $type) && (int) $type > 0) {
                    $escaped      = $db->escape($type);
                    $conditions[] = "p.tag IN (SELECT DISTINCT resource FROM {$trT} WHERE value = 'fiche_bazar' AND property = '{$typeProp}')";
                    $conditions[] = "p.body LIKE '%\"id_typeannonce\":\"{$escaped}\"%'";
                }
                break;
        }

        if ($tagFilter !== '') {
            $escaped = $db->escape($tagFilter);
            // tag filter uses the already-joined tg alias, so use HAVING
            $having  = "HAVING GROUP_CONCAT(DISTINCT tg.value ORDER BY tg.value SEPARATOR ',') LIKE '%{$escaped}%'";
        }

        $aclCondition = $this->buildAclFilterCondition($db, $aclFilter);
        if ($aclCondition !== null) {
            $conditions[] = $aclCondition;
        }

        return [implode(' AND ', $conditions), $having];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildAclFilterCondition(DbService $db, string $aclFilter): ?string
    {
        if ($aclFilter === '') {
            return null;
        }
        $parts = explode('|', $aclFilter, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$privilege, $value] = $parts;
        $aclCols = ['read' => 'a_r.list', 'write' => 'a_w.list', 'comment' => 'a_c.list'];
        if (!isset($aclCols[$privilege])) {
            return null;
        }
        $col = $aclCols[$privilege];
        // Escape MySQL REGEXP metacharacters, then escape for SQL
        $regexpEscaped = $db->escape(preg_replace('/([.+*?\\[\\]^$(){}|\\\\])/', '\\\\$1', $value));
        // Match value as a complete line within the ACL text
        return "({$col} REGEXP '(^|\\n|\\r){$regexpEscaped}(\\n|\\r|$)')";
    }

    private function filterCommentAcl(string $list): string
    {
        $filtered = implode(',', array_filter(
            explode(',', $list),
            fn ($e) => $e !== '' && trim($e) !== '' && trim($e) !== '*'
        ));

        return $filtered === '' ? 'comments-closed' : $filtered;
    }

    private function getForms(): array
    {
        try {
            return $this->getService(\YesWiki\Bazar\Service\FormManager::class)->getAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function htmlResponse(string $html, int $code = 200): Response
    {
        return new Response($html, $code, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
