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
	die ("accés direct interdit");
}

class ActionEdithandlersacls extends WikiniAdminAction
{
    function PerformAction($args, $command)
    {
        $wiki = &$this->wiki;
        $list = $wiki->GetHandlersList();
        sort($list);
        $res = $wiki->FormOpen('', '', 'get');
        $res .= _t('HANDLER_RIGHTS').' <select name="handlername">';
        foreach ($list as $handler)
        {
        	$res .= '<option value="' . $handler . '"';
            if (!empty($_GET['handlername']) && $_GET['handlername'] == $handler) $res .= ' selected="selected"';
            $res .= '>' . ucfirst($handler) .  '</option>';
        }
        $res .= '</select> <input class="btn btn-default" type="submit" value="'._t('SEE').'" />' . $wiki->FormClose();
        
        if ($_POST && !empty($_POST['handlername'])) // save ACL's
        {
        	$result = $wiki->SetModuleACL($name = $_POST['handlername'], 'handler', @$_POST['acl']);
        	if ($result)
        	{
        		return $res . _t('ERROR_WHILE_SAVING_HANDLER_ACL').' ' . ucfirst($name) . ' ('._t('ERROR_CODE').' ' . $result . ')<br />';
        	}
        	else
        	{
        		$wiki->LogAdministrativeAction($wiki->GetUserName(), _t('NEW_ACL_FOR_HANDLER')." " . ucfirst($name) . ' : ' . @$_POST['acl'] . "\n");
        		return $res . _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER').' ' . ucfirst($name) . '.<br />';
        	}
        }
        elseif (!empty($_GET['handlername']) && in_array($name = $_GET['handlername'], $list))
        {
        	$res .= $wiki->FormOpen();
        	$res .= '<br />'._t('EDIT_RIGHTS_FOR_HANDLER').' <strong>' . ucfirst($name) . '</strong>: <br />';
        	$res .= '<input type="hidden" name="handlername" value="'. $name . '" />';
        	$res .= '<textarea class="form-control" name="acl" rows="3">' . $wiki->GetModuleACL($name, 'handler') . '</textarea><br />'; 
        	$res .= '<input type="submit" value="'._t('SAVE').'" class="btn btn-primary" accesskey="s" />';
			return $res . $wiki->FormClose();
        }
        return $res;
    }
}
?>
