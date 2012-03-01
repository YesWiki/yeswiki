<?php
/*
usersettings.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright 2002  Patrick PAUL
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
if (!isset($_REQUEST["action"])) $_REQUEST["action"] = '';
if ($_REQUEST["action"] == "logout")
{
	$this->LogoutUser();
	$this->SetMessage("Vous &ecirc;tes maintenant d&eacute;connect&eacute; !");
	$this->Redirect($this->href());
}
else if ($user = $this->GetUser())
{
	
	// is user trying to update?
	if ($_REQUEST["action"] == "update")
	{
		$this->Query("update ".$this->config["table_prefix"]."users set ".
			"email = '".mysql_escape_string($_POST["email"])."', ".
			"doubleclickedit = '".mysql_escape_string($_POST["doubleclickedit"])."', ".
			"show_comments = '".mysql_escape_string($_POST["show_comments"])."', ".
			"revisioncount = '".mysql_escape_string($_POST["revisioncount"])."', ".
			"changescount = '".mysql_escape_string($_POST["changescount"])."', ".
			"motto = '".mysql_escape_string($_POST["motto"])."' ".
			"where name = '".$user["name"]."' limit 1");
		
		$this->SetUser($this->LoadUser($user["name"]));
		
		// forward
		$this->SetMessage("Param&egrave;tres sauvegard&eacute;s !");
		$this->Redirect($this->href());
	}
	
	if ($_REQUEST["action"] == "changepass")
	{
			// check password
			$password = $_POST["password"];			
			if (preg_match("/ /", $password)) $error = "Les espaces ne sont pas permis dans les mots de passe.";
			else if (strlen($password) < 5) $error = "Mot de passe trop court.";
			else if ($user["password"] != md5($_POST["oldpass"])) $error = "Mauvais mot de passe."; 
			else
			{
				$this->Query("update ".$this->config["table_prefix"]."users set "."password = md5('".mysql_escape_string($password)."') "."where name = '".$user["name"]."'");				
				$this->SetMessage("Mot de passe chang&eacute; !");
				$user["password"]=md5($password);
				$this->SetUser($user);
				$this->Redirect($this->href());
			}
	}
	// user is logged in; display config form
	echo $this->FormOpen();
	?>
	<input type="hidden" name="action" value="update" />
	<table>
		<tr>
			<td align="right"></td>
			<td>Bonjour, <?php echo $this->Link($user["name"]) ?>&nbsp;!</td>
		</tr>
		<tr>
			<td align="right">Votre adresse de messagerie &eacute;lectronique&nbsp;:</td>
			<td><input name="email" value="<?php echo htmlspecialchars($user["email"]) ?>" size="40" /></td>
		</tr>
		<tr>
			<td align="right">&Eacute;dition en double-cliquant&nbsp;:</td>
			<td><input type="hidden" name="doubleclickedit" value="N" /><input type="checkbox" name="doubleclickedit" value="Y" <?php echo $user["doubleclickedit"] == "Y" ? "checked=\"checked\"" : "" ?> /></td>
		</tr>
		<tr>
			<td align="right">Par d&eacute;faut, montrer les commentaires&nbsp;:</td>
			<td><input type="hidden" name="show_comments" value="N" /><input type="checkbox" name="show_comments" value="Y" <?php echo $user["show_comments"] == "Y" ? "checked\"checked\"" : "" ?> /></td>
		</tr>
		<tr>
			<td align="right">Nombre maximum de derniers commentaires&nbsp;:</td>
			<td><input name="changescount" value="<?php echo htmlspecialchars($user["changescount"]) ?>" size="40" /></td>
		</tr>
		<tr>
			<td align="right">Nombre maximum de versions&nbsp;:</td>
			<td><input name="revisioncount" value="<?php echo htmlspecialchars($user["revisioncount"]) ?>" size="40" /></td>
		</tr>
		<tr>
			<td align="right">Votre devise&nbsp;:</td>
			<td><input name="motto" value="<?php echo htmlspecialchars($user["motto"]) ?>" size="40" /></td>
		</tr>
		<tr>
			<td></td>
			<td><input type="submit" value="Mise &agrave; jour" /> <input type="button" value="D&eacute;connexion" onclick="document.location='<?php echo $this->href("", "", "action=logout"); ?>'" /></td>
		</tr>
	</table>

	<?php
	echo $this->FormClose();

	echo $this->FormOpen();
	?>
	<input type="hidden" name="action" value="changepass" />
	<table>
		<tr>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td align="right"></td>
			<td><?php echo $this->Format("Changement de mot de passe"); ?></td>
		</tr>
		<?php
		if (isset($error))
		{
			echo "<tr><td></td><td><div class=\"error\">", $this->Format($error), "</div></td></tr>\n";
		}
		?>
		<tr>
			<td align="right">Votre ancien mot de passe&nbsp;:</td>
			<td><input type="password" name="oldpass" size="40" /></td>
		</tr>
		<tr>
			<td align="right">Nouveau mot de passe&nbsp;:</td>
			<td><input type="password" name="password" size="40" /></td>
		</tr>
		<tr>
			<td></td>
			<td><input type="submit" value="Changer" size="40" /></td>
		</tr>
	</table>
	<?php
	echo $this->FormClose();

}
else
{
	// user is not logged in
	
	// is user trying to log in or register?
	if ($_REQUEST["action"] == "login")
	{
		// if user name already exists, check password
		if ($existingUser = $this->LoadUser($_POST["name"]))
		{
			// check password
			if ($existingUser["password"] == md5($_POST["password"]))
			{
				$this->SetUser($existingUser, $_POST["remember"]);
				$this->Redirect($this->href('', '', 'action=checklogged', false));
			}
			else
			{
				$error = "Mauvais mot de passe&nbsp;!";
			}
		}
		// otherwise, create new account
		else
		{
			$name = trim($_POST["name"]);
			$email = trim($_POST["email"]);
			$password = $_POST["password"];
			$confpassword = $_POST["confpassword"];

			// check if name is WikkiName style
			if (!$this->IsWikiName($name)) $error = "Votre nom d'utilisateur doit &ecirc;tre format&eacute; en NomWiki.";
			else if (!$email) $error = "Vous devez sp&eacute;cifier une adresse de messagerie &eacute;lectronique.";
			else if (!preg_match("/^.+?\@.+?\..+$/", $email)) $error = "Ceci ne ressemble pas &agrave; une adresse de messagerie &eacute;lectronique.";
			else if ($confpassword != $password) $error = "Les mots de passe n'&eacute;taient pas identiques";
			else if (preg_match("/ /", $password)) $error = "Les espaces ne sont pas permis dans un mot de passe.";
			else if (strlen($password) < 5) $error = "Mot de passe trop court. Un mot de passe doit contenir au minimum 5 caract&egrave;res alphanum&eacute;riques.";
			else
			{
				$this->Query("insert into ".$this->config["table_prefix"]."users set ".
					"signuptime = now(), ".
					"name = '".mysql_escape_string($name)."', ".
					"email = '".mysql_escape_string($email)."', ".
					"password = md5('".mysql_escape_string($_POST["password"])."')");

				// log in
				$this->SetUser($this->LoadUser($name));

				// forward
				$this->Redirect($this->href());
			}
		}
	}
	elseif ($_REQUEST['action'] == 'checklogged')
	{
		$error = 'Vous devez accepter les cookies pour pouvoir vous connecter.';
	}

	echo $this->FormOpen();
	?>
	<input type="hidden" name="action" value="login" />
	<table>
		<tr>
			<td></td>
			<td><?php echo $this->Format("Si vous &ecirc;tes d&eacute;j&agrave; enregistr&eacute;, identifiez-vous ici"); ?></td>
		</tr>
		<?php
		if (isset($error))
		{
			echo "<tr><td></td><td><div class=\"error\">", $this->Format($error), "</div></td></tr>\n";
		}
		?>
		<tr>
			<td align="right">Votre NomWiki&nbsp;:</td>
			<td><input name="name" size="40" value="<?php if (isset($name)) echo htmlspecialchars($name) ?>" /></td>
		</tr>
		<tr>
			<td align="right">Mot de passe (5 caract&egrave;res minimum)&nbsp;:</td>
			<td>
			<input type="password" name="password" size="40" />
			<input type="hidden" name="remember" value="0" />
			<input type="checkbox" name="remember" value="1" />&nbsp;Se souvenir de moi.
			</td>
		</tr>
		<tr>
			<td></td>
			<td><input type="submit" value="Identification" size="40" /></td>
		</tr>
		<tr>
			<td></td>
			<td><?php echo $this->Format("Les champs suivants sont &agrave; remplir si vous vous identifiez pour la premi&egrave;re fois (vous cr&eacute;erez ainsi un compte)"); ?></td>
		</tr>
		<tr>
			<td align="right">Confirmation du mot de passe&nbsp;:</td>
			<td><input type="password" name="confpassword" size="40" /></td>
		</tr>
		<tr>
			<td align="right">Adresse de messagerie &eacute;lectronique.&nbsp;:</td>
			<td><input name="email" size="40" value="<?php if (isset($email)) echo htmlspecialchars($email) ?>" /></td>
		</tr>
		<tr>
			<td></td>
			<td><input type="submit" value="Nouveau compte" size="40" /></td>
		</tr>
	</table>
	<?php
	echo $this->FormClose();
}
?>

