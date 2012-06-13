<?php
/*
tagrss.php

Copyright 2011  Florian SCHMITT <florian@outils-reseaux.org>
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
$tags = (isset($_GET['tags'])) ? $_GET['tags'] : '';
$req = ''; $req_from = ''; $req_group = '';
$textetitre = 'Derniers changements sur '. $this->config["wakka_name"]  ;

header("Content-type: text/xml; charset=ISO-8859-1");
echo '<?xml version="1.0" encoding="iso-8859-1"?>';
//on fait les tableaux pour les tags, puis on met des virgules et des guillemets
if (!empty($tags))
{
	$tags=trim($tags);
	$tab_tags = explode(",", $tags);
	$nbdetags = count($tab_tags);
	$tags = implode(",", array_filter($tab_tags, "trim"));
	$tags = '"'.str_replace(',','","',$tags).'"';
	//ajout dans la requete mysql
	$req .= ' AND tags.value IN ('.$tags.')';
	$req .= ' AND tags.property="http://outils-reseaux.org/_vocabulary/tag" AND tags.resource=tag ';
	$req_group .= ' GROUP BY tag HAVING COUNT(tag)='.$nbdetags.' ';
	//texte utilisé pour la description du flux RSS
	$textetitre .= ', contenant les tags '.$tags;
}

//traitement du type de page
if (!empty($type))
{
	$req_from .= ', '.$this->config["table_prefix"].'triples type ';
	$req .= ' AND type.resource=tag AND type.property="http://outils-reseaux.org/_vocabulary/type" AND type.value="'.$type.'" ';
}

//REQUETE DE SELECTION DES PAGES
$requete = 'SELECT DISTINCT tag, time, user, owner, body FROM '.$this->config["table_prefix"].'pages, '.$this->config["table_prefix"].'triples tags '.$req_from.' WHERE latest = "Y" and comment_on = "" '.$req.$req_group;

//gestion du tri de l'affichage
if (!empty($tri))
{
	if ($tri == "alpha")
	{
		$requete .= ' ORDER BY tag ASC';
	}
	elseif ($tri == "date")
	{
		$requete .= ' ORDER BY time DESC';
	}
}
else //par defaut on tri par date
{
	$requete .= ' ORDER BY time DESC';
}
$requete .= ' LIMIT 0,50';
//echo $requete;break;
//on a des resultats!
if ($pages = $this->LoadAll($requete)) {
	if (!($link = $this->GetParameter("link"))) {
		$link=$this->config["root_page"];
	}
	//header("Content-Type: application/rss+xml");
	$output = "<rss version=\"2.0\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
	$output .= "<channel>\n<title>";
	if (empty($titrerss)) {
		$output .= $textetitre;
	} else {
		$output .= $titrerss;
	}
	$output .= "</title>\n";
	$output .= "<link>" . $this->config["base_url"] . $link . "</link>\n";
	$output .= "<description>".$textetitre."</description>\n";
	$output .= "<atom:link href=\"". $this->Href('xml') ."\" rel=\"self\" type=\"application/rss+xml\" />\n";
	$items = '';
	foreach ($pages as $page)
	{
		$items .= "<item>\r\n";
		$items .= "<title>".$page['tag']."</title>\r\n";
		$items .= "<link>" . $this->config["base_url"] . $page["tag"] . "</link>\r\n";
		$items .= "<description><![CDATA[";

		//on enleve les actions recentchangesrssplus pour eviter les boucles infinies, avant de formater en HTML le texte
		$page["body"] = preg_replace("/\{\{recentchangesrss(.*?)\}\}/s", '', $page["body"]);
		$page["body"] = preg_replace("/\{\{rss(.*?)\}\}/s", '', $page["body"]);
		if (strstr($page["body"], "bf_titre")) {
			$tab_valeurs = json_decode($page["body"], true);
			$tab_valeurs = array_map('utf8_decode', $tab_valeurs);
			$page["body"] = '""'.baz_voir_fiche(0, $tab_valeurs).'""';
		}
		$texteformat = $this->Format($page['body']);

		//on tronque le texte apres le prochain espace
		if (!empty($nb_caracteres_rss) && (strlen($texteformat) > $nb_caracteres_rss) )
		{
			$texteformat = substr($texteformat, 0, $nb_caracteres_rss);
			$last_space = strrpos($texteformat, " ");
			$texteformat = substr($texteformat, 0, $last_space)."<br /><a href=\"".$this->config["base_url"] . $page["tag"] . "\" title=\"Lire la suite\">Lire la suite</a>";
		}

		$items .= $texteformat . "]]></description>\r\n";
		$items .= "<dc:creator>by ".htmlspecialchars($page["user"])."</dc:creator>\r\n";
		$items .= "<pubDate>" . gmdate('D, d M Y H:i:s \G\M\T', strtotime($page['time'])) . "</pubDate>\r\n";
		$itemurl = $this->href(false, $page["tag"], "time=" . htmlspecialchars(rawurlencode($page["time"])));
		$items .= '<guid>' . $itemurl . "</guid>\n";
		$items .= "</item>\r\n";
	}

	$output .= $items;
	$output .= "</channel>\n";
	$output .= "</rss>\n";
	die("\n".$output);
}
?>

?>