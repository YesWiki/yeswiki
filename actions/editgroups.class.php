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
if (!defined("WIKINI_VERSION"))
{
	die ("accés direct interdit");
}

class ActionEditgroups extends WikiniAdminAction
{
	function PerformAction($args, $command)
	{
		$wiki = &$this->wiki;
		$list = $wiki->GetGroupsList();
		if (!$wiki->UserIsAdmin())
		{
			$list = array_diff($list, array(ADMIN_GROUP));
		}
		sort($list);
		$res = $wiki->FormOpen('', '', 'get', 'form-inline');
		$res .= _t('DEFINITION_OF_THE_GROUP').'<select name="groupname">';
		foreach ($list as $group)
		{
			$res .= '<option value="' . htmlspecialchars($group, ENT_COMPAT, YW_CHARSET) . '"';
			if (!empty($_GET['groupname']) && $_GET['groupname'] == $group) $res .= ' selected="selected"';
			$res .= '>' . htmlspecialchars($group, ENT_COMPAT, YW_CHARSET) .  '</option>';
		}
		$res .= '</select>'."\n".'<input class="btn btn-default" type="submit" value="'._t('SEE').'" />'."\n" . $wiki->FormClose();
		$res .= $wiki->FormOpen('', '', 'get', 'form-inline') . _t('CREATE_NEW_GROUP').': <input type="text" required="required" name="groupname" />';
		$res .= '<input class="btn btn-default" type="submit" value="'._t('DEFINE').'" />' . $wiki->FormClose();

		if ($_POST && !empty($_POST['groupname']) && isset($_POST['acl'])) // save ACL's
		{
			$name = $_POST['groupname'];
			$newacl = $_POST['acl'];
			if (strtolower($name) == ADMIN_GROUP)
			{
				if (!$wiki->UserIsAdmin())
				{
					return $res . _t('ONLY_ADMINS_CAN_CHANGE_MEMBERS') .'.<br/>';
				}
				if (!$wiki->CheckACL($newacl))
				{
					return $res . _t('YOU_CANNOT_REMOVE_YOURSELF').'.<br/>';
				}
			}
			$result = $wiki->SetGroupACL($name, $newacl);
			if ($result)
			{
				if ($result == 1000)
				{
					return $res . _t('ERROR_RECURSIVE_GROUP').' !<br />';
				}
				else
				{
					return $res . _t('ERROR_WHILE_SAVING_GROUP') . ' ' . ucfirst($name) . ' ('._t('ERROR_CODE').' ' . $result . ')<br />';
				}
			}
			else
			{
				$wiki->LogAdministrativeAction($wiki->GetUserName(), _t('NEW_ACL_FOR_GROUP')." " . ucfirst($name) . ' : ' . $newacl . "\n");
				return $res . _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_THE_GROUP').' ' . ucfirst($name) . '.<br />';
			}
		}
		elseif (!empty($_GET['groupname']))
		{
			$name = $_GET['groupname'];
			if (!preg_match('/[^A-Za-z0-9]/', $name))
			{
				$res .= $wiki->FormOpen();
				$res .= '<br />'._t('EDIT_GROUP').' <strong>' . htmlspecialchars($name, ENT_COMPAT, YW_CHARSET) . '</strong>: <br />';
				$res .= '<input type="hidden" name="groupname" value="'. $name . '" />';
				$res .= '<textarea name="acl" rows="3" class="form-control">' . (in_array($name, $list) ? $wiki->GetGroupACL($name) : '') . '</textarea><br />'; 
				$res .= '<input type="submit" value="'._t('SAVE').'" class="btn btn-primary" accesskey="s" />';
				return $res . $wiki->FormClose();
			}
			else
			{
				$res .= _t('ONLY_ALPHANUM_FOR_GROUP_NAME').'.';
			}
		}
		return $res;
	}
}
?>
