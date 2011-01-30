<?php
/*
An action allowing to edit the ACL of the handlers

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

class ActionEdithandlersacls extends WikiniAdminAction
{
    function PerformAction($args)
    {
        $wiki = &$this->wiki;
        $list = $wiki->GetHandlersList();
        sort($list);
        $res = $wiki->FormOpen('', '', 'get');
        $res .= 'Droits du handler <select name="handlername">';
        foreach ($list as $handler)
        {
        	$res .= '<option value="' . $handler . '">' . ucfirst($handler) .  '</option>';
        }
        $res .= '</select> <input type="submit" value="voir" />' . $wiki->FormClose();
        
        if ($_POST && !empty($_POST['handlername'])) // save ACL's
        {
        	$result = $wiki->SetModuleACL($name = $_POST['handlername'], 'handler', @$_POST['acl']);
        	if ($result)
        	{
        		return $res . 'Une erreur s\'est produite pendant l\'enregistrement de l\'ACL pour le handler ' . ucfirst($name) . ' (code d\'erreur ' . $result . ')<br />';
        	}
        	else
        	{
        		$wiki->LogAdministrativeAction($wiki->GetUserName(), "Nouvelle ACL pour le handler " . ucfirst($name) . ' : ' . @$_POST['acl'] . "\n");
        		return $res . 'Nouvelle ACL enregistr&eacute;e avec succ&egrave;s pour le handler ' . ucfirst($name) . '.<br />';
        	}
        }
        elseif (!empty($_GET['handlername']) && in_array($name = $_GET['handlername'], $list))
        {
        	$res .= $wiki->FormOpen();
        	$res .= '<br />&Eacute;diter les droits du handler <strong>' . ucfirst($name) . '</strong>: <br />';
        	$res .= '<input type="hidden" name="handlername" value="'. $name . '" />';
        	$res .= '<textarea name="acl" rows="4" cols="20">' . $wiki->GetModuleACL($name, 'handler') . '</textarea><br />'; 
        	$res .= '<input type="submit" value="Enregistrer" style="width: 120px" accesskey="s" />';
			return $res . $wiki->FormClose();
        }
        return $res;
    }
}
?>
