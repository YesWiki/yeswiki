<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 1999-2006 outils-reseaux.org                                                           |
// +------------------------------------------------------------------------------------------------------+
// | This file is part of convergence.                                                                        |
// |                                                                                                      |
// | Foobar is free software; you can redistribute it and/or modify                                       |
// | it under the terms of the GNU General Public License as published by                                 |
// | the Free Software Foundation; either version 2 of the License, or                                    |
// | (at your option) any later version.                                                                  |
// |                                                                                                      |
// | Foobar is distributed in the hope that it will be useful,                                            |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                                        |
// | GNU General Public License for more details.                                                         |
// |                                                                                                      |
// | You should have received a copy of the GNU General Public License                                    |
// | along with Foobar; if not, write to the Free Software                                                |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// CVS : $Id: convergence.php,v 1.5 2011-07-13 10:24:11 mrflos Exp $
/**
* convergence.php
*
* Description :
*
*@package contact
//Auteur original :
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@author        Edouard ALAVOINE <ealavoine@yahoo.fr>
*@copyright     outils-reseaux.org 2011
*@version       $Revision: 1.5 $ $Date: 2011-07-13 10:24:11 $
// +------------------------------------------------------------------------------------------------------+
*/

if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

$width = $this->GetParameter("width");
if (empty($width)) $width = '500';

$height = $this->GetParameter("height");
if (empty($height)) $height = '500';

$upaxisname = $this->GetParameter("upaxis");
if (empty($upaxisname)) $upaxisname = 'J\'aime';

$downaxisname = $this->GetParameter("downaxis");
if (empty($downaxisname)) $downaxisname = 'J\'aime pas';

$rightaxisname = $this->GetParameter("rightaxis");
if (empty($rightaxisname)) $rightaxisname = 'Autres idées';

$refreshtime = $this->GetParameter("refreshtime");
if (empty($refreshtime)) $refreshtime = '1200';

$cooldowntime = $this->GetParameter("cooldowntime");
if (empty($cooldowntime)) $cooldowntime = '3000';

$evolution = $this->GetParameter("evolution");
if (empty($evolution)) $evolution = '100';

// on crée un id de session pour savoir qui vote, valable 24h, mais cela est transparent pour l'utilisateur car caché
$sessionCookieExpireTime=24*60*60;
session_set_cookie_params($sessionCookieExpireTime);
$id = session_id();
if(empty($id)) {
	session_start();
	$id = session_id();
}
echo '<input type="hidden" id="sessionid" name="sessionid" value="'.$id.'" />';

// affichage du canvas (graphique pour le vote)
echo '<canvas id="votecanvas" height="'.$height.'" width="'.$width.'" style="border:1px solid #444;margin:10px auto;display:block;">
<p>Votre navigateur ne supporte pas canvas... Utilisez Firefox ou Chrome!</p>
</canvas>
<button id="fullscreen" type="button">Plein &eacute;cran</button>
<button id="savepicture" type="button">Enregistrer le vote dans une image</button><br /><br /><br />';

// l'administrateur peut faire des actions spéciales
if ($this->UserIsAdmin()) {
	echo	'<label for="votetitle">Titre du vote</label><input id="votetitle" name="votetitle" type="text" value=""/>
			<button id="changetitle" type="button">Changer le titre du vote</button><br /><br />
			<button id="resetvote" type="button">R&eacute;initialiser tous les votes</button>';	
}

// partie js du code
$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').'<script type="text/javascript">
	Modernizr.load([
		{
			load: \'tools/convergence/libs/convergence.js\',
			complete: function () {
				$(\'#votecanvas\').bind(\'dblclick\', function(e) {
					return false;
				}).convergence({
					\'upaxisname\'		: \''.addslashes($upaxisname).'\',
					\'downaxisname\'	: \''.addslashes($downaxisname).'\',
					\'rightaxisname\'	: \''.addslashes($rightaxisname).'\',
					\'refresh\'			: \''.$refreshtime.'\',
					\'cooldowntime\'	: \''.$cooldowntime.'\',
					\'evolution\'			: \''.$evolution.'\'
				});
			}
		 }
	]);
</script>'."\n";

?>
