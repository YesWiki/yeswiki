<?php
/*
resetpassword.php

Copyright 2003  Patrick PAUL
Copyright 2003 David DELON
Copyright 2004  David VANTYGHEM
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


if (($user = $this->GetUser()) && ($user["name"]==$this->GetConfigValue("admin")) && $this->GetConfigValue("admin"))
{

	if (($_REQUEST["action"] == "resetpass"))
	{
			
		$this->Query("update ".$this->config["table_prefix"]."users set ".
					"password = md5('".mysql_escape_string($_POST["password"])."') ".
					"where name = '".mysql_escape_string($_POST["name"])."' limit 1");				
					
				$this->SetMessage(_t('PASSWORD_UPDATED')." !");
				$this->Redirect($this->href());
	}
	else
	{
		$error="";
		//$error = "Il est interdit de r&eacute;initialiser le mot de passe de cet utilisateur ! Non mais !";
	}
	
	echo $this->FormOpen() ;
	$name=$_GET["name"];
	?>
	<input type="hidden" name="action" value="resetpass">
	<table>
		<tr>
			<td align="right"></td>
			<td><?php echo _t('RESETTING_THE_PASSWORD'); ?></td>
		</tr>
		<?php
		if ($error)
		{
			echo "<tr><td></td><td><div class=\"alert alert-danger error\">".$this->Format($error)."</div></td></tr>\n" ;
		}
		?>
		<tr>
			<td align="right"><?php echo _t('WIKINAME'); ?> :</td>
			<td><input name="name" size="40" value="<?php echo  $name ?>"></td>
		</tr>
		<tr>
			<td align="right"><?php echo _t('NEW_PASSWORD'); ?> :</td>
			<td><input type="password" name="password" size="40"></td>
		</tr>
		<tr>
			<td></td>
			<td><input type="submit" value="<?php echo _t('RESET_PASSWORD'); ?>" size="40"></td>
		</tr>
	</table>
	<?php
	echo $this->FormClose() ;
}
else
{
	echo "<div class=\"alert alert-danger\"><strong>"._t('ACTION_RESETPASSWORD')."</strong> : "._t('NO_PERMISSIONS_TO_EXECUTE_THIS_ACTION').".</div>\n" ;
}

?>
	
