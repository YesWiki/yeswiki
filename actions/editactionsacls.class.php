<?php
/*
An action allowing to edit the ACL of the other actions

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

class ActionEditactionsacls extends WikiniAdminAction
{
    function PerformAction($args)
    {
        $wiki = &$this->wiki;
        $list = $wiki->GetActionsList();
        sort($list);
        $res = $wiki->FormOpen('', '', 'get');
        $res .= 'Droits de l\'action <select name="actionname">';
        foreach ($list as $action)
        {
        	$res .= '<option value="' . $action . '">' . ucfirst($action) .  '</option>';
        }
        $res .= '</select> <input type="submit" value="voir" />' . $wiki->FormClose();
        
        if ($_POST && !empty($_POST['actionname'])) // save ACL's
        {
        	$result = $wiki->SetModuleACL($name = $_POST['actionname'], 'action', @$_POST['acl']);
        	if ($result)
        	{
        		return $res . 'Une erreur s\'est produite pendant l\'enregistrement de l\'ACL pour l\'action ' . ucfirst($name) . ' (code d\'erreur ' . $result . ')<br />';
        	}
        	else
        	{
        		$wiki->LogAdministrativeAction($wiki->GetUserName(), "Nouvelle ACL pour l'action " . ucfirst($name) . ' : ' . @$_POST['acl'] . "\n");
        		return $res . 'Nouvelle ACL enregistr&eacute;e avec succ&egrave;s pour l\'action ' . ucfirst($name) . '.<br />';
        	}
        }
        elseif (!empty($_GET['actionname']) && in_array($name = $_GET['actionname'], $list))
        {
        	$res .= $wiki->FormOpen();
        	$res .= '<br />&Eacute;diter les droits de l\'action <strong>' . ucfirst($name) . '</strong>: <br />';
        	$res .= '<input type="hidden" name="actionname" value="'. $name . '" />';
        	$res .= '<textarea name="acl" rows="4" cols="20">' . $wiki->GetModuleACL($name, 'action') . '</textarea><br />'; 
        	$res .= '<input type="submit" value="Enregistrer" style="width: 120px" accesskey="s" />';
			return $res . $wiki->FormClose();
        }
        return $res;
    }
}
?>
