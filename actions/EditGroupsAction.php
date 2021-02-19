<?php
/*
An action allowing to edit the ACL of the groups

Copyright 2005  Didier Loiseau
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

use YesWiki\Core\YesWikiAction;

class EditGroupsAction extends YesWikiAction
{
    public function run()
    {
        // Form definition
        $wiki = &$this->wiki;
        $list = $wiki->GetGroupsList(); // retrieves an array of group names from table 'triples' (content of 'resource' starts with 'ThisWikiGroup' and content of 'property' equals  'http://www.wikini.net/_vocabulary/acls')
        if (!$wiki->UserIsAdmin()) { // If user not in admin group, remove admin group from the list
            $list = array_diff($list, array(ADMIN_GROUP));
        }
        sort($list);
        // Start of group edition
        $res = $wiki->FormOpen('', '', 'get', 'form-inline');
        $res .= '<label>Editer un groupe existant</label><p><select class="form-control" name="groupname">';
        foreach ($list as $group) {
            $res .= '<option value="' . htmlspecialchars($group, ENT_COMPAT, YW_CHARSET) . '"';
            if (!empty($_GET['groupname']) && $_GET['groupname'] == $group) {
                $res .= ' selected="selected"';
            }
            $res .= '>' . htmlspecialchars($group, ENT_COMPAT, YW_CHARSET) .  '</option>';
        }
        $res .= '</select>'."\n".'<input class="btn btn-primary btn-edit-group" type="submit" value="Voir / Editer" /></p>'."\n" . $wiki->FormClose();
        // End of group edition
        // Start of group creation
        $res .= $wiki->FormOpen('', '', 'get', 'form-inline') . '<label>' . _t('CREATE_NEW_GROUP').'</label><p> <input type="text" name="groupname" placeholder="Nom du groupe" class="form-control" />';
        $res .= '<input class="btn btn-primary btn-create-group" type="submit" value="'._t('DEFINE').'" /></p>' . $wiki->FormClose();
        // End of group creation
        // Start of group deletion
        $res .= $wiki->FormOpen('', '', 'get', 'form-inline');
        $res .= '<label>Supprimer un groupe existant</label>';
        $res .= '<p><select class="form-control" name="deletegroup">';
        foreach ($list as $group) {
            $res .= '<option value="' . htmlspecialchars($group, ENT_COMPAT, YW_CHARSET) . '"';
            if (!empty($_GET['deletegroup']) && $_GET['deletegroup'] == $group) {
                $res .= ' selected="selected"';
            }
            $res .= '>' . htmlspecialchars($group, ENT_COMPAT, YW_CHARSET) .  '</option>';
        }
        $res .= '</select>'."\n".'<input class="btn btn-danger btn-delete-group" type="submit" value="Supprimer" /></p>'."\n" . $wiki->FormClose();
        // End of group deletion
        // End of form definition

        // Form action handling
        if ($_POST && !empty($_POST['groupname']) && isset($_POST['acl'])) { // save ACL's
        // The form method is 'post'
        // it returns a groupname and list of users (acl), therefore
        // The group has been edited
            $name = $_POST['groupname'];
            $newacl = $_POST['acl'];
            if (strtolower($name) == ADMIN_GROUP) {
                if (!$wiki->UserIsAdmin()) {
                    return $res . _t('ONLY_ADMINS_CAN_CHANGE_MEMBERS') .'.<br/>';
                }
                if (!$wiki->CheckACL($newacl)) {
                    return $res . _t('YOU_CANNOT_REMOVE_YOURSELF').'.<br/>';
                }
            }
            $result = $wiki->SetGroupACL($name, $newacl);
            if ($result) {
                if ($result == 1000) {
                    return $res . _t('ERROR_RECURSIVE_GROUP').' !<br />';
                } else {
                    return $res . _t('ERROR_WHILE_SAVING_GROUP') . ' ' . ucfirst($name) . ' ('._t('ERROR_CODE').' ' . $result . ')<br />';
                }
            } else {
                $wiki->LogAdministrativeAction($wiki->GetUserName(), _t('NEW_ACL_FOR_GROUP')." " . ucfirst($name) . ' : ' . $newacl . "\n");
                return $res . _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_THE_GROUP').' ' . ucfirst($name) . '.<br />';
            }
            // The group has been edited – End
        } elseif (!empty($_GET['deletegroup'])) {
            // The form returns a groupname to delete, therefore
            // There is a request to delete the group
            $name = $_GET['deletegroup'];
            if ($wiki->GetGroupACL($name) != '') { // The group is not empty
                $res .= _t('ONLY_EMPTY_GROUP_FOR_DELETION').'.';
            } else {
                // Check if acls table contains at least one line (page)
                // for which this group is the only one to have some privilege
                $sql = 'SELECT page_tag FROM ' . $wiki->GetConfigValue('table_prefix') . 'acls WHERE list = "@' . $name . '"';
                $ownedPages = array();
                $ownedPages = $wiki->LoadAll($sql); // if group owns no pages, then empty
                if ($ownedPages) {
                    // Array is not empty because the query returns at least one page
                    $res .= _t('ONLY_NO_PAGES_GROUP_FOR_DELETION').'.';
                    foreach ($ownedPages as $ownedPage) {
                        $res .= '<br/>' . $ownedPage['page_tag'];
                    }
                    return $res;
                } else {
                    // Group is empty AND is not alone having privvileges on any page
                    /* create sql connection*/
                    $link = mysqli_connect(
                        $GLOBALS["wiki"]->config['mysql_host'],
                        $GLOBALS["wiki"]->config['mysql_user'],
                        $GLOBALS["wiki"]->config['mysql_password'],
                        $GLOBALS["wiki"]->config['mysql_database']
                    );
                    /* Build sql query*/
                    // ACLS part
                    $aclsTable = $GLOBALS["wiki"]->config['table_prefix'].WIKINI_VOC_ACLS;
                    $searched_value = '%@' . $name . '%';
                    $seek_value_bf = '@' . $name . '\n'; // groupname to delete can be followed by another groupname
                $seek_value_af = '\n@' . $name; // groupname to delete can follow another groupname
                // get rid of this groupname everytime it's followed by another
                $sql = "UPDATE ".$aclsTable."	SET list = REPLACE (list, '".$seek_value_bf."', '') WHERE list LIKE '" . $searched_value . "';";
                    // in the remaining get rid of this groupname everytime it follows another
                    $sql .= "\nUPDATE ".$aclsTable." SET list = REPLACE (list, '".$seek_value_af."', '') WHERE list LIKE '" . $searched_value . "';";
                    // End of ACLS part
                    // Triples part
                    $triplesTable = $GLOBALS["wiki"]->config['table_prefix'].'triples';
                    $groupName = GROUP_PREFIX . $name;
                    $sql .= "\nDELETE FROM ".$triplesTable." WHERE resource = '".$groupName."' AND value = '';";
                    // End of triples part
                    /* Execute queries */
                    mysqli_multi_query($link, $sql);
                    do {
                        ;
                    } while (mysqli_next_result($link));
                    return $res . 'groupe ' . $name . ' supprimé' . '<br/>';
                }
            }
        } elseif (!empty($_GET['groupname'])) {
            // The form returns a groupname and no list of users (acl), therefore
            // Request to edit the group
            $name = $_GET['groupname'];
            if (!preg_match('/[^A-Za-z0-9]/', $name)) { // only alphanumeric characters
                $res .= $wiki->FormOpen(); // form method is 'post' by default
                $res .= '<hr><label class="edit-group">Liste des membres du groupe <strong>' . htmlspecialchars($name, ENT_COMPAT, YW_CHARSET) . '</strong></label> (un nom d\'utilisateur par ligne)';
                $res .= '<input type="hidden" name="groupname" value="'. $name . '" />';
                $res .= '<textarea name="acl" rows="3" class="form-control">' . (in_array($name, $list) ? $wiki->GetGroupACL($name) : '') . '</textarea><br />';
                $res .= '<input type="submit" value="'._t('SAVE').'" class="btn btn-primary" accesskey="s" />';
                return $res . $wiki->FormClose();
            } else { // groupname contains characters other than alphanumeric
                $res .= _t('ONLY_ALPHANUM_FOR_GROUP_NAME').'.';
            }
        }
        // Request to edit the group – End
        return $res;
        // Form action handling – End
    }
}
