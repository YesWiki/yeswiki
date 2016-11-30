<?php
/*
$Id: deletepage.php 858 2007-11-22 00:46:30Z nepote $
Copyright 2002  David DELON
Copyright 2003  Eric FELDSTEIN
Copyright 2004  Jean Christophe ANDRé
Copyright 2006  Didier Loiseau
Copyright 2007  Charles NéPOTE
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

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

if ($this->UserIsOwner() || $this->UserIsAdmin())
{
	if ($this->IsOrphanedPage($this->GetPageTag()))
	{
		$tag = $this->GetPageTag();
		if (!$_GET['confirme'] == 'oui')
		{			
			$msg = '<form action="'.$this->Href('deletepage', "", "confirme=oui");
			$msg .= '" method="post" style="display: inline">'."\n";
			$msg .= 'Voulez-vous vraiment supprimer d&eacute;finitivement la page '.$this->Link($tag)."&nbsp;?\n";
			$msg .= '<input type="submit" value="Oui" ';
			$msg .= 'style="vertical-align: middle; display: inline" />'."\n";
			$msg .= "</form>\n";			
			$msg .= '<form action="'.$this->Href().'" method="post" style="display: inline">'."\n";
			$msg .= '<input type="submit" value="Non" style="vertical-align: middle; display: inline" />'."\n";
			$msg .= "</form></span>\n";			
		}
		else
		{
			$this->DeleteOrphanedPage($tag);
			$this->LogAdministrativeAction($this->GetUserName(), "Suppression de la page ->\"\"" . $tag . "\"\"");
			$msg = "La page ${tag} a d&eacute;finitivement &eacute;t&eacute; supprim&eacute;e";
		}
	}
	else
	{
		$msg = "<p><em>Cette page n'est pas orpheline.</em></p>\n";
		$linkedFrom = $this->LoadAll("SELECT DISTINCT from_tag " .
									 "FROM ".$this->config["table_prefix"]."links " .
									 "WHERE to_tag = '".$this->GetPageTag()."'");
		$msg .= "<p>Pages ayant un lien vers ".
				$this->ComposeLinkToPage($this->tag, "", "", 0)." :</p>\n";
		$msg .= "<ul>\n";
		foreach ($linkedFrom as $page)
		{
			$msg .= "<li>".$this->ComposeLinkToPage($page["from_tag"],"","",0)."</li>\n";
		}
		$msg .= "</ul>\n";
	}

}
else
{
	$msg = "<p><em>Vous n'&ecirc;tes pas le propri&eacute;taire de cette page.</em></p>\n";
}


echo $this->Header();
echo "<div class=\"page\">\n";
echo $msg;
echo "</div>\n";
echo $this->Footer();
?>
