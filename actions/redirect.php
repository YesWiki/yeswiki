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
	echo '<div class="alert alert-danger"><strong>'._t('ERROR_ACTION_REDIRECT').'</strong> : '._t('MISSING_PAGE_PARAMETER').'.</div>'."\n";
} 
elseif ($this->GetMethod() == 'show')
{
	if (!isset($_SESSION['redirects'])) $_SESSION['redirects'] = array();
	$_SESSION['redirects'][] = strtolower($this->GetPageTag());

	if (in_array(strtolower($redirPageName), $_SESSION['redirects']))
	{
		echo "<div class=\"alert alert-danger\"><strong>"._t('ERROR_ACTION_REDIRECT')."</strong> : "._t('CIRCULAR_REDIRECTION_FROM_PAGE')." $redirPageName ( "
		 . $this->ComposeLinkToPage($redirPageName, 'edit',  call_user_func('_t', 'CLICK_HERE')) . ')</div>'."\n";
	} 
	else
	{
		$this->Redirect($this->Href('', $redirPageName));
	} 
}
else
{
	echo '<span style="color: red; weight: bold">'._t('PRESENCE_OF_REDIRECTION_TO').' "' . $this->Link($redirPageName) . '"</span>';
}

?>