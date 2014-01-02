<?php
/*
$Id: ajaxdeletepage.php,v 1.2 2009-10-12 16:10:32 mrflos Exp $
Copyright 2002  David DELON
Copyright 2003  Eric FELDSTEIN
Copyright 2004  Jean Christophe ANDRÉ
Copyright 2006  Didier Loiseau
Copyright 2007  Charles NÉPOTE
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

// Verification de securite
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

// on ne fait quelque chose uniquement dans le cas d'une requete jsonp
if (isset($_GET['jsonp_callback'])) 
{
	// on initialise la sortie:
	header('Content-type:application/json');
	if ($this->UserIsOwner() || $this->UserIsAdmin()) {	
		$tag = $this->GetPageTag();
		$this->DeleteOrphanedPage($tag);
		// on supprime les mots cles associes a la page
		$this->DeleteAllTags($tag);
		$this->LogAdministrativeAction($this->GetUserName(), "Suppression de la page ->\"\"" . $tag . "\"\"");
		echo $_GET['jsonp_callback']."(".json_encode(array("reponse"=>utf8_encode("succes"))).")";
	} 
	else {
		echo $_GET['jsonp_callback']."(".json_encode(array("reponse"=>utf8_encode("interdit"))).")";
	}
}
?>
