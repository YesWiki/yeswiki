<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\LinkRenderer;
use YesWiki\Render\Service\MarkdownFormatterService;

/**
 * `{{listpages}}` -- converted from the procedural actions/listpages.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class ListpagesAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'listpages';
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
        /*
        * WikiNi action allowing to list pages among different ways

        * Parameters:
        *  - sort: specifies the sorting order; by time, user (last editor), owner or tag (page name)
        *  - owner: list all pages belonging to the given user
        *  - exclude: list of page that should not be listed
        *  - user: list all pages to which the given user has taken part
        */

        // retrieve parameters
        $sort = strtolower($this->getService(PerformableArguments::class)->get('sort'));
        $owner = $this->getService(PerformableArguments::class)->get('owner');
        $exclude = $this->getService(PerformableArguments::class)->get('exclude');
        $user = $this->getService(PerformableArguments::class)->get('user');

        // default values
        // use a secure $sort value for MySQL
        if (!in_array($sort, ['time', 'user', 'owner', 'tag'])) {
            $sort = 'tag';
        }
        if ($owner == 'owner') {
            $owner = $this->getService(PageManager::class)->getOwner();
        }
        if (($owner && $sort == 'owner') || ($user && $sort == 'user')) {
            $sort = 'tag';
        }
        if ($exclude) {
            // no addslashes(): the names are bound now, so escaping them here would filter on
            // the escaped spelling instead of on the tag the author typed
            $exclude = preg_split('/[ ;,\|]/', (string)$exclude, -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $exclude = [];
        }
        if ($user == 'user') {
            $user = $this->getService(PageManager::class)->getOwner();
        }

        $prefix = $this->getService(RuntimeConfig::class)->getValue('table_prefix');

        // treatment
        $dbService = $this->getService(DbService::class);
        $userCol = $dbService->quoteIdentifier('user');

        // classical list display
        // building the request
        //
        // These joins used to read a `users` table, which this major dropped: accounts are
        // `pages` rows carrying `type = 'user'` now. Every branch below that sorted or filtered
        // by user therefore died with "no such table: <prefix>users" -- only the plainest branch
        // (no user, no owner, sort != user) has no join and kept working, which is why the
        // action looked healthy.
        //
        // One join replaces the two: an account's row IS the page its name points at, so
        // "is registered" and "has an own page" are now the same question. The third rendering
        // branch below (registered, but no page of their own) is consequently unreachable --
        // that is the unification working, not a case gone missing.
        $typeCol = $dbService->quoteIdentifier('type');
        $timeCol = $dbService->quoteIdentifier('time');
        $accountFlags = 'user_row.tag IS NOT NULL user_is_registered, user_row.tag IS NOT NULL user_has_ownpage';
        $params = [];

        // `a` is the latest revision in every branch, so the clauses appended after this block
        // (parent, exclusions, ORDER BY) qualify one alias and read the same throughout.
        $latestRow = ' FROM ' . $prefix . 'pages a';
        $accountJoin = ' LEFT JOIN ' . $prefix . "pages user_row ON user_row.tag = a.{$userCol}"
            . " AND user_row.latest = 'Y' AND user_row.{$typeCol} = ?";
        $ownerJoin = ' LEFT JOIN ' . $prefix . "pages owner_page ON a.owner = owner_page.tag AND owner_page.latest = 'Y'";

        if ($user) {
            // "pages this user took part in" is a question about *any* revision, which used to
            // be asked by joining the table to itself and collapsing the duplicates with a
            // GROUP BY. That grouping selected non-aggregated columns, which SQLite tolerates
            // and both MySQL (ONLY_FULL_GROUP_BY) and PostgreSQL reject outright -- so the
            // first repair of this branch still only ran on one of the three drivers. EXISTS
            // asks the same question without producing duplicates to collapse.
            $params[] = PageType::USER;
            $sql = "SELECT a.tag, a.{$timeCol}, a.{$userCol}, {$accountFlags}"
                . ($owner ? '' : ', a.owner, owner_page.tag IS NOT NULL owner_has_ownpage')
                . $latestRow
                . $accountJoin
                . ($owner ? '' : $ownerJoin)
                . " WHERE a.latest = 'Y'"
                . ' AND EXISTS (SELECT 1 FROM ' . $prefix . "pages r WHERE r.tag = a.tag AND r.{$userCol} = ?)"
                . ($owner ? ' AND a.owner = ?' : '');
            $params[] = $user;
            if ($owner) {
                $params[] = $owner;
            }
        } elseif ($owner) {
            if ($sort == 'user') {
                $params[] = PageType::USER;
                $sql = "SELECT a.tag, a.{$timeCol}, a.{$userCol}, {$accountFlags}" . $latestRow . $accountJoin;
            } else {
                $sql = "SELECT a.tag, a.{$timeCol}" . $latestRow;
            }
            $sql .= " WHERE a.owner = ? AND a.latest = 'Y'";
            $params[] = $owner;
        } else {
            if ($sort == 'user') {
                $params[] = PageType::USER;
                $sql = 'SELECT a.tag, a.owner, owner_page.tag IS NOT NULL owner_has_ownpage,'
                    . " a.{$userCol}, {$accountFlags}"
                    . $latestRow . $accountJoin . $ownerJoin;
            } else {
                $sql = "SELECT a.tag, a.owner, a.{$timeCol}, owner_page.tag IS NOT NULL owner_has_ownpage"
                    . $latestRow . $ownerJoin;
            }
            $sql .= " WHERE a.latest = 'Y'";
        }
        $sql .= " AND a.parent = ''";
        if ($exclude) {
            $sql .= ' AND a.tag NOT IN (' . SqlParameters::placeholders(count($exclude)) . ')';
            $params = [...$params, ...$exclude];
        }
        // `$sort` is whitelisted above, but two of its four values (`time`, `user`) are
        // reserved words on at least one driver, so the column is quoted rather than pasted.
        // The empty-string comparisons were written `= ""`, which is a MySQL-ism: PostgreSQL
        // reads a double-quoted token as an *identifier*, so it errored on a column named "".
        if ($sort == 'owner') {
            // this allows to display non existent pages last
            $sql .= " ORDER BY a.owner = '', a.owner";
        } else {
            $sql .= ' ORDER BY a.' . $dbService->quoteIdentifier($sort);
        }

        // retrieving the pages
        $pages = $this->getService(DbService::class)->loadAll($sql, $params);

        // Display
        // Header
        if ($user) {
            echo _t('PAGE_LIST_WHERE') . ' ' . $this->getService(MarkdownFormatterService::class)->format($user) . ' ' . _t('HAS_PARTICIPATED');
            if ($owner) {
                echo ' ' . _t('INCLUDING') . ' ' . $this->getService(LinkRenderer::class)->link($owner) . ' ' . _t('IS_THE_OWNER');
            }
            if ($exclude) {
                echo ' (' . _t('EXCLUDING_EXCLUSIONS') . ')';
            }
            echo ":\n";
            if (!$pages) {
                echo "<br />\n" . _t('NO_PAGE_FOUND') . "...<br />\n";

                return;
            }
        } elseif ($owner) {
            echo _t('LIST_PAGES_BELONGING_TO') . ' ' . $this->getService(LinkRenderer::class)->link($owner);
            if ($exclude) {
                echo ' (' . _t('EXCLUDING_EXCLUSIONS') . ')';
            }
            echo ":\n";
            if (!$pages) {
                echo "<br />\n" . _t('THIS_USER_HAS_NO_PAGE') . "...\n<br />\n";

                return;
            }
        } elseif (!$pages) {
            // because it is still possible...
            echo _t('NO_PAGE_FOUND') . ' ' . _t('IN_THIS_WIKI') . ' (' . _t('EXCLUDING_EXCLUSIONS') . ')';

            return;
        }
        // No header if it is a simple page list that was asked

        // Display the list itself
        echo "<ul>\n";
        $aclService = $this->getService(\YesWiki\Identity\Service\AclService::class);
        foreach ($pages as $page) {
            if ($aclService->hasAccess('read', $page['tag'])) {
                echo "\t<li>" . $this->getService(LinkRenderer::class)->linkToPage($page['tag'], false, false);
                if (!$owner) {
                    echo ' . . . . ';
                    if ($page['owner']) {
                        if ($page['owner_has_ownpage']) {
                            echo $this->getService(LinkRenderer::class)->linkToPage($page['owner'], false, false);
                        } else {
                            echo '<span class="forced-link missingpage">' . $page['owner'] . '</span>';
                            echo $this->getService(LinkRenderer::class)->linkToPage($page['owner'], 'edit', '?');
                        }
                    } else {
                        echo _t('UNKNOWN');
                    }
                }
                if ($sort == 'user' || $sort == 'time') {
                    echo '  . . . . <strong>' . _t('LAST_CHANGE') . '</strong>';
                    if ($sort == 'time') {
                        echo ': ' . $page['time'];
                    }
                    if ($sort == 'user' || ($user && $sort == 'time')) {
                        echo ' <strong>' . _t('BY') . '</strong> ';
                        if ($page['user_is_registered']) {
                            if ($page['user_has_ownpage']) {
                                echo $this->getService(LinkRenderer::class)->linkToPage($page['user'], false, false);
                            } else {
                                echo '<span class="forced-link missingpage">' . $page['user'] . '</span>';
                                echo $this->getService(LinkRenderer::class)->linkToPage($page['user'], 'edit', '?');
                            }
                        } else {
                            echo htmlspecialchars($page['user'], ENT_COMPAT, YW_CHARSET);
                        }
                    }
                }
                echo "</li>\n";
            }
        }
        echo "</ul>\n";
    }
}
