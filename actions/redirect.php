<?php
/**
 * redirect.php : Permet de faire une redirection vers une autre pages Wiki du site
 * 
 * @Copyright 2003  Eric FELDSTEIN
 * @Copyright 2003  David DELON
 * @Copyright 2004  Jean Christophe ANDRE
 * @Copyright 2005  Didier Loiseau
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 **/

/*
Parametres : page : nom wiki de la page vers laquelle ont doit rediriger (obligatoire)
exemple : {{redirect page="BacASable"}}
*/

$redirPageName = $this->GetParameter('page');

if (!$redirPageName)
{
	echo '<em><strong>Erreur ActionRedirect</strong>: Le param&ecirc;tre "page" est manquant.</em>';
} 
elseif ($this->GetMethod() == 'show')
{
	if (!isset($_SESSION['redirects'])) $_SESSION['redirects'] = array();
	$_SESSION['redirects'][] = strtolower($this->GetPageTag());

	if (in_array(strtolower($redirPageName), $_SESSION['redirects']))
	{
		echo "<em><strong>Erreur ActionRedirect</strong>: redirection circulaire depuis la page $redirPageName (cliquez "
		 . $this->ComposeLinkToPage($redirPageName, 'edit', 'ici') . ' pour l\'&eacute;diter)</em>';
	} 
	else
	{
		$this->Redirect($this->Href('', $redirPageName));
	} 
}
else
{
	echo '<span style="color: red; weight: bold">Pr&eacute;sence d\'une redirection vers "' . $this->Link($redirPageName) . '"</span>';
}

?>