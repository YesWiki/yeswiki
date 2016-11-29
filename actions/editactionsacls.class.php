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
	die ("accés direct interdit");
}

class ActionEditactionsacls extends WikiniAdminAction
{
    function PerformAction($args, $command)
    {
        $wiki = &$this->wiki;
        $list = $wiki->GetActionsList();
        sort($list);
        $res = $wiki->FormOpen('', '', 'get');
        $res .= _t('ACTION_RIGHTS').' <select name="actionname">';
        foreach ($list as $action)
        {
        	$res .= '<option value="' . $action . '"';
            if (!empty($_GET['actionname']) && $_GET['actionname'] == $action) $res .= ' selected="selected"';
            $res .= '>' . ucfirst($action) .  '</option>';
        }
        $res .= '</select> <input type="submit" class="btn btn-default" value="'._t('SEE').'" />' . $wiki->FormClose();
        
        if ($_POST && !empty($_POST['actionname'])) // save ACL's
        {
        	$result = $wiki->SetModuleACL($name = $_POST['actionname'], 'action', @$_POST['acl']);
        	if ($result)
        	{
        		return $res . _t('ERROR_WHILE_SAVING_ACL') .' ' . ucfirst($name) . ' ('._t('ERROR_CODE').' ' . $result . ')<br />';
        	}
        	else
        	{
        		$wiki->LogAdministrativeAction($wiki->GetUserName(), _t('NEW_ACL_FOR_ACTION')." " . ucfirst($name) . ' : ' . @$_POST['acl'] . "\n");
        		return $res . _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION').' ' . ucfirst($name) . '.<br />';
        	}
        }
        elseif (!empty($_GET['actionname']) && in_array($name = $_GET['actionname'], $list))
        {
        	$res .= $wiki->FormOpen();
        	$res .= '<br />'._t('EDIT_RIGHTS_FOR_ACTION').' <strong>' . ucfirst($name) . '</strong>:';
        	$res .= '<input type="hidden" name="actionname" value="'. $name . '" />';
        	$res .= '<textarea class="form-control" name="acl" rows="3">' . $wiki->GetModuleACL($name, 'action') . '</textarea><br />'; 
        	$res .= '<input type="submit" value="'._t('SAVE').'" class="btn btn-primary" accesskey="s" />';
			return $res . $wiki->FormClose();
        }
        return $res;
    }
}
?>
