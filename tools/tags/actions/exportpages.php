<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Florian Schmitt <florian@outils-reseaux.org>                                      |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// 
/**
*
* Export de toutes les pages en derniere version, au format txt
*
*
*@package templates
*
*@author        Florian Schmitt <florian@outils-reseaux.org>
*
*@copyright     Outils-Reseaux 2012
*@version       $Revision: 0.1 $ $Date: 2010/03/04 14:19:03 $
*/

if (!defined("WIKINI_VERSION")) {
    die ("acc&egrave;s direct interdit");
}

include_once 'tools/tags/libs/tags.functions.php';

// ajoute t on les pages installees par defaut dans wiki
$addinstalledpage = $this->getParameter('addinstalledpage');

// quels types de pages : fiche bazar, page wiki, ou tout?
$type = $this->getParameter('type');
if ($type!='bazar' && $type!='wiki' && $type!='all') $type = 'all';

$output = '';

if (isset($_POST["page"])) {
	foreach ($_POST["page"] as $page) {
		echo $this->Format('{{include page="'.$page.'"}}');
	}
}
else {
	// recuperation des pages creees a l'installation
	$d = dir("setup/doc/");
	while ($doc = $d->read()){
		if (is_dir($doc) || substr($doc, -4) != '.txt')
			continue;
		if ($doc=='_root_page.txt'){
			$installpagename[$this->GetConfigValue("root_page")] = $this->GetConfigValue("root_page");
		} else {
			$pagename = substr($doc,0,strpos($doc,'.txt'));
			$installpagename[$pagename] = $pagename;
		}
	}	

	// recuperation des pages wikis
	$sql = 'SELECT tag,body FROM '.$this->GetConfigValue('table_prefix').'pages WHERE latest="Y" 
				AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';

	if ($type == 'wiki') {
		$sql .= ' AND tag NOT IN (SELECT resource FROM '.$this->GetConfigValue('table_prefix').'triples WHERE property="http://outils-reseaux.org/_vocabulary/type") ';
	} 
	elseif ($type == 'bazar') {
		$sql .= ' AND tag IN (SELECT resource FROM '.$this->GetConfigValue('table_prefix').'triples WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar") ';
	}

	$sql .= 'ORDER BY tag';

	$pages = $this->LoadAll($sql);

	// on prend tous les tags
	$sql = 'SELECT DISTINCT value FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag"';
	$tags = $this->LoadAll($sql);

	include_once 'tools/tags/libs/squelettephp.class.php';
	$template_export = new SquelettePhp('tools/tags/presentation/templates/exportpages_table.tpl.html'); // charge le templates
	$template_export->set(array('pages' => $pages, 'addinstalledpage' => $addinstalledpage, 'installedpages' => $installpagename, 'tags' => $tags, 'url' => $this->href('',$this->GetPageTag()))); // on passe le tableau de pages en parametres
	$output .= $template_export->analyser(); // affiche les resultats
}

echo "\n$output\n";
