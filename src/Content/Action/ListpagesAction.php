<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
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
            // notice we can addslash() the list before splitting it because escaped character are not separators
            $exclude = preg_split('/[ ;,\|]/', addslashes($exclude), -1, PREG_SPLIT_NO_EMPTY);
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
        // has_ownpage and user_is_registered avoid us to make requests to know
        // whether the personnal pages of owners and users exist
        if ($user) {
            $sql = "SELECT a.tag, b.time,
                b.$userCol, name IS NOT NULL user_is_registered, user_page.tag IS NOT NULL user_has_ownpage"
                . ($owner ? '' : ', b.owner, owner_page.tag IS NOT NULL owner_has_ownpage')
                . ' FROM ' . $prefix . 'pages a, ' . $prefix . 'pages b
                LEFT JOIN ' . $prefix . 'users ON b.user = name
                LEFT JOIN ' . $prefix . 'pages user_page ON name = user_page.tag AND user_page.latest = "Y"'
                . ($owner ? '' : ' LEFT JOIN ' . $prefix . 'pages owner_page ON b.owner = owner_page.tag AND owner_page.latest = "Y"')
                . ' WHERE a.user = "' . $this->getService(DbService::class)->escape($user) . '"'
                . ' AND a.tag = b.tag AND b.latest = "Y"'
                . ($owner ? ' AND b.owner = "' . $this->getService(DbService::class)->escape($owner) . '"' : '');
        } elseif ($owner) {
            if ($sort == 'user') {
                $sql = "SELECT a.tag, a.time,
                    a.$userCol, name IS NOT NULL user_is_registered, user_page.tag IS NOT NULL user_has_ownpage
                    FROM " . $prefix . 'pages a
                    LEFT JOIN ' . $prefix . "users ON a.$userCol = name
                    LEFT JOIN " . $prefix . "pages user_page ON name = user_page.tag AND user_page.latest = 'Y'";
            } else {
                $sql = 'SELECT tag, time FROM ' . $prefix . 'pages a';
            }
            $sql .= ' WHERE a.owner = "' . $this->getService(DbService::class)->escape($owner) . '" AND a.latest = "Y"';
        } else {
            if ($sort == 'user') {
                $sql = "SELECT a.tag, a.owner,
                    owner_page.tag IS NOT NULL owner_has_ownpage,
                    a.$userCol, name IS NOT NULL user_is_registered, user_page.tag IS NOT NULL user_has_ownpage
                    FROM " . $prefix . 'pages a
                    LEFT JOIN ' . $prefix . "users ON a.$userCol = name
    		LEFT JOIN " . $prefix . "pages user_page ON name = user_page.tag AND user_page.latest = 'Y'
    		LEFT JOIN " . $prefix . "pages owner_page ON a.owner = owner_page.tag AND owner_page.latest = 'Y'";
            } else {
                $sql = 'SELECT a.tag, a.owner, a.time, b.tag IS NOT NULL owner_has_ownpage
                    FROM ' . $prefix . 'pages a
                    LEFT JOIN ' . $prefix . "pages b ON a.owner = b.tag AND b.latest = 'Y'";
            }
            $sql .= " WHERE a.latest = 'Y'";
        }
        $sql .= " AND a.parent = ''";
        if ($exclude) {
            $sql .= " AND a.tag NOT IN ('" . implode("', '", $exclude) . "')";
        }
        if ($user) {
            $sql .= ' GROUP BY tag';
            if ($sort == 'owner') {
                $sql .= ' ORDER BY b.owner = "", b.owner';
            } else {
                $sql .= ' ORDER BY b.' . $sort;
            }
        } elseif ($sort == 'owner') {
            // this allows to display non existent pages last
            $sql .= ' ORDER BY a.owner = "", a.owner';
        } else {
            $sql .= ' ORDER BY a.' . $sort;
        }

        // retrieving the pages
        $pages = $this->getService(DbService::class)->loadAll($sql);

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
