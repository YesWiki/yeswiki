<?php
/**
* WikiNi action allowing to list pages among different ways
* 
* @version $Id: listpages.php 833 2007-08-10 01:16:57Z gandon $
* @author Didier Loiseau
* @copyright 2005   Didier Loiseau
* @license This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
* @todo tree & levels
*/

/*
* Parameters:
*  - tree: tree display starting at some page
*       by default: if owner is not specified, the main page
*           else, the page of the user specified by the owner parameter
*  - levels: the depth of the tree
*  - sort: specifies the sorting order; by time, user (last editor), owner or tag (page name)
*  - owner: if tree is specified, only list pages whose owner is the given user
*           else, list all pages belonging to the given user
*  - exclude: list of page that should not be listed (including their descendents)
*  - user: list all pages to which the given user has taken part
*    (cannot be combined with tree)
*/

// retrieve parameters
$sort = strtolower($this->GetParameter('sort'));
$tree = $this->GetParameter('tree');
$levels = (int) $this->GetParameter('levels');
$max_levels = 7;
$owner = $this->GetParameter('owner');
$exclude = $this->GetParameter('exclude');
$user = $this->GetParameter('user');

// default values
// use a secure $sort value for MySQL
if (!in_array($sort, array('time', 'user', 'owner', 'tag')))
{
    $sort = 'tag';
} 
if ($owner == 'owner')
{
    $owner = $this->GetPageOwner();
} 
if (($owner && $sort == 'owner') || ($user && $sort == 'user'))
{
    $sort = 'tag';
}
if ($tree == 'tree')
{
    if ($owner)
    {
        $tree = $owner;
    }
    else
    {
        $tree = $this->GetConfigValue('root_page');
    }
} 
if ($levels <= 0)
{
    $levels = 3;
} 
elseif ($levels > $max_levels)
{
    $levels = $max_levels;
}
if ($exclude)
{
    // notice we can addslash() the list before splitting it because escaped character are not separators
    $exclude = preg_split('/[ ;,\|]/', addslashes($exclude), -1, PREG_SPLIT_NO_EMPTY);
} 
else
{
    $exclude = array();
}
if ($user == 'user')
{
    $user = $this->GetPageOwner();
}

$prefix = $this->GetConfigValue('table_prefix');

// treatment
if ($tree)
// tree display
{
    /* first step: retrieve every pages of the tree:
     * $links will be built according to the following template:
     * 'tag' => array(
     *  'page_exists' => true|false, // whether the page exists (avoids 1 request for each page...)
     *  'haslinksto'  => array( // list of pages to which this one is linked
     *      ['tag' => etc.[, ...]] // data are stored in a recursive tree
     *  )
     *  [, additionnal info[, ...]] // modification date, owner (+ does he have his own page ?), user (+ idem and is he registered ?)
     * )
     */
    $links = array();

    // informations on the root page
    switch ($sort)
    {
        case 'owner':
            $sql = 'SELECT a.owner, b.tag IS NOT NULL owner_has_ownpage'
                . ' FROM ' . $prefix . 'pages a'
                . ' LEFT JOIN ' . $prefix . 'pages b ON a.owner = b.tag AND b.latest = "Y"';
                break;
        case 'user':
            $sql = 'SELECT a.user, u.name IS NOT NULL user_is_registered, b.tag IS NOT NULL user_has_ownpage'
                . ' FROM ' . $prefix . 'pages a'
                . ' LEFT JOIN ' . $prefix . 'users u ON a.user = u.name'
                . ' LEFT JOIN ' . $prefix . 'pages b ON u.name = b.tag AND b.latest = "Y"';
            break;
        case 'time':
            $sql = 'SELECT a.time'
                . ' FROM ' . $prefix . 'pages a';
            break;
        case 'tag':
            $links[$tree] = array();
    } // switch
    if ($sort != 'tag')
    {
        $sql .= ' WHERE a.tag = "' . AddSlashes($tree) . '" AND a.latest = "Y" LIMIT 1';
        if (!$rootData = $this->LoadSingle($sql))
        {
            echo '<div class="alert alert-danger"><strong>'._t('ERROR').' '._t('ACTION').' ListPages</strong> : '._('THE_PAGE').' ' . htmlspecialchars($tree, ENT_COMPAT, YW_CHARSET) . ' '._t('DOESNT_EXIST').' !</div>';
            return;
        }
        $links[$tree] = $rootData;
    }
    $links[$tree]['page_exists'] = true;
    $links[$tree]['haslinksto'] = array();
    
    // To simplify treatment and to make it more efficient we'll work by referrence.
    // This will allow you to do only one request by tree level
    // $workingon represents every page of the current level
    $workingon = array($tree => &$links[$tree]['haslinksto']);
    
    // to avoid many loops and computing several time the lists needed for the request,
    // we store them into variables
    $from = '"' . AddSlashes($tree) . '"';
    $exclude[] = AddSlashes($tree);
    $exclude_str = '"' . implode('", "', $exclude) . '"';
    for ($i = 1; $i <= $levels; $i++)
    {
        if ($from)
        {
            if ($owner)
            {
                $sql = 'SELECT from_tag, to_tag, a.tag IS NOT NULL page_exists'
                    . ($sort == 'time' ? ', a.time' : '');
                if ($sort == 'user')
                {
                    $sql .= ', a.user, u.name IS NOT NULL user_is_registered, b.tag IS NOT NULL user_has_ownpage'
                        . ' FROM ' . $prefix . 'links, ' . $prefix . 'pages a'
                        . ' LEFT JOIN ' . $prefix . 'users u ON a.user = u.name'
                        . ' LEFT JOIN ' . $prefix . 'pages b ON u.name = b.tag AND b.latest = "Y"';
                }
                else
                {
                    $sql .= ' FROM ' . $prefix . 'links, ' . $prefix . 'pages a';
                }
                $sql .= ' WHERE from_tag IN (' . $from . ')'
                    . ' AND to_tag NOT IN (' . $from . ')'
                    . ' AND to_tag = a.tag'
                    . ' AND a.owner = "' . AddSlashes($owner) . '"'
                    . ' AND a.latest = "Y"';
            }
            else
            {
                $sql = 'SELECT from_tag, to_tag, a.tag IS NOT NULL page_exists';
                switch ($sort)
                {
                    case 'owner':
                        $sql .= ', a.owner, b.tag IS NOT NULL owner_has_ownpage'
                            . ' FROM ' . $prefix . 'links'
                            . ' LEFT JOIN ' . $prefix . 'pages a ON to_tag = a.tag AND a.latest = "Y"'
                            . ' LEFT JOIN ' . $prefix . 'pages b ON a.owner = b.tag AND b.latest = "Y"';
                        break;
                    case 'user':
                        $sql .= ', a.user, u.name IS NOT NULL user_is_registered, b.tag IS NOT NULL user_has_ownpage'
                            . ' FROM ' . $prefix . 'links'
                            . ' LEFT JOIN ' . $prefix . 'pages a ON to_tag = a.tag AND a.latest = "Y"'
                            . ' LEFT JOIN ' . $prefix . 'users u ON a.user = u.name'
                            . ' LEFT JOIN ' . $prefix . 'pages b ON u.name = b.tag AND b.latest = "Y"';
                        break;
                    case 'time':
                        $sql .= ', a.time';
                    default:
                        $sql .= ' FROM ' . $prefix . 'links'
                            . ' LEFT JOIN ' . $prefix . 'pages a ON to_tag = a.tag AND a.latest = "Y"';
                } // switch
                $sql .= ' WHERE from_tag IN (' . $from . ')'
                    . ' AND to_tag NOT IN (' . $exclude_str . ')';
            }
        }
        // result order
        $sql .= ' ORDER BY ';
        switch ($sort)
        {
            case 'tag':
                $sql .= 'to_tag';
                break;
            case 'owner':
                // 1) existing pages having an owner, sorted by owner name
                // 2) existing pages without owner
                // 3) non-existent pages
                $sql .= 'a.owner IS NULL, a.owner = "", a.owner';
                break;
            case 'time':
                // 1) existing pages, sorted in antechronologic order
                // 2) non-existent pages
                $sql .= 'a.time IS NULL, a.time DESC';
                break;
            case 'user':
                // 1) existing pages, sorted by last editor
                // 2) non-existent pages
                $sql .= 'a.user IS NULL, a.user';
                break;
        } // switch
        
        if ($pages = $this->LoadAll($sql))
        {
            $from = '';
            $newworkingon = array();
            foreach ($pages as $page)
            {
                $to_tag = '"' . AddSlashes($page['to_tag']) . '"';
                $workingon[$page['from_tag']][$page['to_tag']] = array('page_exists' => $page['page_exists'], 'haslinksto' => array());
                if ($sort != 'tag')
                {
                    $workingon[$page['from_tag']][$page['to_tag']][$sort] = $page[$sort];
                    switch ($sort)
                    {
                        case 'owner':
                            $workingon[$page['from_tag']][$page['to_tag']]['owner_has_ownpage'] = $page['owner_has_ownpage'];
                            break;
                        case 'user':
                            $workingon[$page['from_tag']][$page['to_tag']]['user_is_registered'] = $page['user_is_registered'];
                            $workingon[$page['from_tag']][$page['to_tag']]['user_has_ownpage'] = $page['user_has_ownpage'];
                            break;
                    } // switch
                }
                if ($page['page_exists'])
                {
                    $from .= ($from ? ', ' : '') . $to_tag;
                    // if several pages link to the same page, only display the tree once
                    // (for the first appearing time)
                    if (!isset($newworkingon[$page['to_tag']]))
                    {
                        $newworkingon[$page['to_tag']] = &$workingon[$page['from_tag']][$page['to_tag']]['haslinksto'];
                    }
                }
                $exclude_str .= ', ' . $to_tag;
            }
            if (!$workingon = $newworkingon)
            {
                // no page had link to still non-referrenced pages, we can stop here
                break;
            }
        }
        else
        {
            // no page was found at this tree level, we can stop here
            break;
        }
    }
    
    // Seccond step: display the tree
    // this function allows us to render the tree using HTML lists.
    if (!function_exists('ShowPageTree'))
    {
        function ShowPageTree($tree, &$wiki, $show = 'tag', $indent = 0)
        {
            if ($tree)
            {
                $indentStr = str_repeat("\t", $indent);
                $retour =  "$indentStr<ul>\n";
                foreach ($tree as $pageName => $pageData)
                {
                    $retour .= "$indentStr\t<li>";
                    if ($pageData['page_exists'])
                    {
                        $retour .= $wiki->ComposeLinkToPage($pageName, false, false, false);
                        switch ($show)
                        {
                            case 'owner':
                                $retour .= ' . . . . '._t('BELONGING_TO').' : ';
                                if ($pageData['owner'])
                                {
                                    if ($pageData['owner_has_ownpage'])
                                    {
                                        $retour .= $wiki->ComposeLinkToPage($pageData['owner'], false, false, false);
                                    }
                                    else
                                    {
                                        $retour .= '<span class="missingpage">' . $pageData['owner'] . '</span>';
                                        $retour .= $wiki->ComposeLinkToPage($pageData['owner'],'edit', '?', false);
                                    }
                                }
                                else
                                {
                                    $retour .= _t('UNKNOWN');
                                }
                                break;
                            case 'user':
                                $retour .= ' . . . . '._t('LAST_CHANGE_BY').' : ';
                                if ($pageData['user_is_registered'])
                                {
                                    if ($pageData['user_has_ownpage'])
                                    {
                                        $retour .= $wiki->ComposeLinkToPage($pageData['user'], false, false, false);
                                    }
                                    else
                                    {
                                        $retour .= '<span class="missingpage">' . $pageData['user'] . '</span>';
                                        $retour .= $wiki->ComposeLinkToPage($pageData['user'],'edit', '?', false);
                                    }
                                }
                                else
                                {
                                    $retour .= $pageData['user'];
                                }
                                break;
                            case 'time':
                                $retour .= ' . . . . '._t('LAST_CHANGE').' : ' . $pageData['time'];
                                break;
                        } // switch
                        if ($pageData['haslinksto'])
                        {
                            $retour .= "\n";
                            $retour .= ShowPageTree($pageData['haslinksto'], $wiki, $show, $indent + 2);
                            $retour .= $indentStr . "\t"; // just put tabs before the </li>
                        }
                    }
                    else
                    {
                        $retour .= '<span class="missingpage">' . $pageName . '</span>'
                            . $wiki->ComposeLinkToPage($pageName,'edit', '?', false);
                    }
                    $retour .= "</li>\n";
                }
                return "$retour$indentStr</ul>\n";
            }
            return '';
        }
    }
    
    echo ShowPageTree($links, $this, $sort);
}
else
// classical list display
{
    // building the request
    // has_ownpage and user_is_registered avoid us to make requests to know
    // whether the personnal pages of owners and users exist
    if ($user)
    {
        $sql = 'SELECT a.tag, b.time,
            b.user, name IS NOT NULL user_is_registered, user_page.tag IS NOT NULL user_has_ownpage'
            . ($owner ? '' : ', b.owner, owner_page.tag IS NOT NULL owner_has_ownpage')
            . ' FROM ' . $prefix . 'pages a, ' . $prefix . 'pages b
            LEFT JOIN ' . $prefix . 'users ON b.user = name
            LEFT JOIN ' . $prefix . 'pages user_page ON name = user_page.tag AND user_page.latest = "Y"'
            . ($owner ? '' : ' LEFT JOIN ' . $prefix . 'pages owner_page ON b.owner = owner_page.tag AND owner_page.latest = "Y"')
            . ' WHERE a.user = "' . AddSlashes($user) . '"'
            . ' AND a.tag = b.tag AND b.latest = "Y"'
            . ($owner ? ' AND b.owner = "' . AddSlashes($owner) . '"' : '');
    }
    elseif ($owner)
    {
        if ($sort == 'user')
        {
            $sql = 'SELECT a.tag, a.time,
                a.user, name IS NOT NULL user_is_registered, user_page.tag IS NOT NULL user_has_ownpage
                FROM ' . $prefix . 'pages a
                LEFT JOIN ' . $prefix . 'users ON a.user = name
                LEFT JOIN ' . $prefix . 'pages user_page ON name = user_page.tag AND user_page.latest = "Y"';
        }
        else
        {
            $sql = 'SELECT tag, time FROM ' . $prefix . 'pages a';
        }
        $sql .= ' WHERE a.owner = "' . AddSlashes($owner) . '" AND a.latest = "Y"';
    }
    else
    {
        if ($sort == 'user')
        {
            $sql = 'SELECT a.tag, a.owner,
                owner_page.tag IS NOT NULL owner_has_ownpage,
                a.user, name IS NOT NULL user_is_registered, user_page.tag IS NOT NULL user_has_ownpage
                FROM ' . $prefix . 'pages a
                LEFT JOIN ' . $prefix . 'users ON a.user = name
		LEFT JOIN ' . $prefix . 'pages user_page ON name = user_page.tag AND user_page.latest = "Y"
		LEFT JOIN ' . $prefix . 'pages owner_page ON a.owner = owner_page.tag AND owner_page.latest = "Y"';
        }
        else
        {
            $sql = 'SELECT a.tag, a.owner, a.time, b.tag IS NOT NULL owner_has_ownpage
                FROM ' . $prefix . 'pages a
                LEFT JOIN ' . $prefix . 'pages b ON a.owner = b.tag AND b.latest = \'Y\'';
        }
        $sql .= ' WHERE a.latest = "Y"';
    }
    $sql .= ' AND a.comment_on = ""';
    if ($exclude)
    {
        $sql .= ' AND a.tag NOT IN ("' . implode('", "', $exclude) . '")';
    }
    if ($user)
    {
        $sql .= ' GROUP BY tag';
        if ($sort == 'owner')
        {
            $sql .= ' ORDER BY b.owner = "", b.owner';
        }
        else
        {
            $sql .= ' ORDER BY b.' . $sort;
        }
    }
    elseif ($sort == 'owner')
    {
        // this allows to display non existent pages last
        $sql .= ' ORDER BY a.owner = "", a.owner';
    }
    else
    {
        $sql .= ' ORDER BY a.' . $sort;
    }
    
    // retrieving the pages
    $pages = $this->LoadAll($sql);
    
    // Display
    // Header
    if ($user)
    {
        echo _t('PAGE_LIST_WHERE').' ' . $this->Format($user) . ' '._t('HAS_PARTICIPATED');
        if ($owner)
        {
            echo ' '._t('INCLUDING').' ' . $this->Link($owner) . ' '._t('IS_THE_OWNER');
        }
        if ($exclude)
        {
            echo ' ('._t('EXCLUDING_EXCLUSIONS').')';
        }
        echo ":\n";
        if (!$pages)
        {
            echo "<br />\n"._t('NO_PAGE_FOUND')."...<br />\n";
            return;
        }
    }
    elseif ($owner)
    {
        echo _t('LIST_PAGES_BELONGING_TO').' ' . $this->Link($owner);
        if ($exclude)
        {
            echo ' ('._t('EXCLUDING_EXCLUSIONS').')';
        }
        echo ":\n";
        if (!$pages)
        {
            echo "<br />\n"._t('THIS_USER_HAS_NO_PAGE')."...\n<br />\n";
            return;
        }
    }
    elseif (!$pages)
    {
        // because it is still possible...
        echo _t('NO_PAGE_FOUND').' '._t('IN_THIS_WIKI').' ('._t('EXCLUDING_EXCLUSIONS').')';
        return;
    }
    // No header if it is a simple page list that was asked
    
    // Display the list itself
    echo "<ul>\n";
    foreach ($pages as $page)
    {
        echo "\t<li>" . $this->ComposeLinkToPage($page['tag'], false, false, false);
        if (!$owner)
        {
            echo ' . . . . ';
            if ($page['owner'])
            {
                if ($page['owner_has_ownpage'])
                {
                    echo $this->ComposeLinkToPage($page['owner'], false, false, false);
                }
                else
                {
                    echo '<span class="missingpage">' . $page['owner'] . '</span>';
                    echo $this->ComposeLinkToPage($page['owner'],'edit', '?', false);
                }
            }
            else
            {
                echo _t('UNKNOWN');
            }
        }
        if ($sort == 'user' || $sort == 'time')
        {
            echo '  . . . . <strong>'._t('LAST_CHANGE').'</strong>';
            if ($sort == 'time')
            {
                echo ': ' . $page['time'];
            }
            if ($sort == 'user' || ($user && $sort == 'time'))
            {
                echo ' <strong>'._t('BY').'</strong> ';
                if ($page['user_is_registered'])
                {
                    if ($page['user_has_ownpage'])
                    {
                        echo $this->ComposeLinkToPage($page['user'], false, false, false);
                    }
                    else
                    {
                        echo '<span class="missingpage">' . $page['user'] . '</span>';
                        echo $this->ComposeLinkToPage($page['user'], 'edit', '?', false);
                    }
                }
                else
                {
                    echo htmlspecialchars($page['user'], ENT_COMPAT, YW_CHARSET);
                }
            }
        }
        echo "</li>\n";
    }
    echo "</ul>\n";
}

?>
