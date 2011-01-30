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
	die ("accès direct interdit");
}

class ActionEditgroups extends WikiniAdminAction
{
	function PerformAction($args)
	{
		$wiki = &$this->wiki;
		$list = $wiki->GetGroupsList();
		if (!$wiki->UserIsAdmin())
		{
			$list = array_diff($list, array(ADMIN_GROUP));
		}
		sort($list);
		$res = $wiki->FormOpen('', '', 'get');
		$res .= 'D&eacute;finition du groupe <select name="groupname">';
		foreach ($list as $group)
		{
			$res .= '<option value="' . htmlspecialchars($group) . '">' . htmlspecialchars($group) .  '</option>';
		}
		$res .= '</select> <input type="submit" value="voir" />' . $wiki->FormClose();
		$res .= $wiki->FormOpen('', '', 'get') . 'Ou cr&eacute;er un nouveau groupe: <input type="text" name="groupname" />';
		$res .= '<input type="submit" value="d&eacute;finir" />' . $wiki->FormClose();

		if ($_POST && !empty($_POST['groupname']) && isset($_POST['acl'])) // save ACL's
		{
			$name = $_POST['groupname'];
			$newacl = $_POST['acl'];
			if (strtolower($name) == ADMIN_GROUP)
			{
				if (!$wiki->UserIsAdmin())
				{
					return $res . 'Vous ne pouvez pas changer les membres du groupe des administrateurs car vous n\'&ecirc;tes pas administrateur.<br/>';
				}
				if (!$wiki->CheckACL($newacl))
				{
					return $res . 'Vous ne pouvez pas vous retirer vous-m&ecirc;me du groupe des administrateurs.<br/>';
				}
			}
			$result = $wiki->SetGroupACL($name, $newacl);
			if ($result)
			{
				if ($result == 1000)
				{
					return $res . 'Erreur: vous ne pouvez pas d&eacute;finir un groupe r&eacute;cursivement !<br />';
				}
				else
				{
					return $res . 'Une erreur s\'est produite pendant l\'enregistrement du groupe ' . ucfirst($name) . ' (code d\'erreur ' . $result . ')<br />';
				}
			}
			else
			{
				$wiki->LogAdministrativeAction($wiki->GetUserName(), "Nouvelle ACL pour le groupe " . ucfirst($name) . ' : ' . $newacl . "\n");
				return $res . 'Nouvelle ACL enr&eacute;gistr&eacute;e avec succ&egrave;s pour le groupe ' . ucfirst($name) . '.<br />';
			}
		}
		elseif (!empty($_GET['groupname']))
		{
			$name = $_GET['groupname'];
			if (!preg_match('/[^A-Za-z0-9]/', $name))
			{
				$res .= $wiki->FormOpen();
				$res .= '<br />&Eacute;diter le groupe <strong>' . htmlspecialchars($name) . '</strong>: <br />';
				$res .= '<input type="hidden" name="groupname" value="'. $name . '" />';
				$res .= '<textarea name="acl" rows="4" cols="20">' . (in_array($name, $list) ? $wiki->GetGroupACL($name) : '') . '</textarea><br />'; 
				$res .= '<input type="submit" value="Enregistrer" style="width: 120px" accesskey="s" />';
				return $res . $wiki->FormClose();
			}
			else
			{
				$res .= 'Les noms de groupes ne peuvent contenir que des caractères alphanum&eacute;riques.';
			}
		}
		return $res;
	}
}
?>
