<?php

// Charles N?pote 2005-2006
// Didier Loiseau 2005
// License GPL.
// Version 0.7.3 du 10/04/2006 ? 23:37.

// TODO
// -- case pour s?lectionner tout
// -- attention au cas o? la version mais aussi la page est effac?e
//   (cf. handler deletepage) (et les commentaires ?)
// -- ne rien loguer si rien n'a ?t? effac?
// -- id?alement la derni?re page affiche les r?sultats mais ne renettoie
//    pas les pages si elle est recharg?e
// -- test pour savoir si quelque chose a bien ?t? effac?


/*$essai = $wiki->GetLinkTable();
$buffer->str( "<pre>";
print_r($essai);
$buffer->str( "</pre>\n";*/


if (!defined("TOOLS_MANAGER"))
{
        die ("acc&egrave;s direct interdit");
}

// Utilisation d'un objet Wiki pour acces ? la base de donn?e

$wiki=new Wiki($wakkaConfig);



$tools_url= "http://".$_SERVER["SERVER_NAME"].($_SERVER["SERVER_PORT"] != 80 ? ":".$_SERVER["SERVER_PORT"] : "").dirname($_SERVER["REQUEST_URI"]).'/tools.php';
$despam_url = $tools_url."?p=despam";


$buffer->str(	"\n<!-- == Action erasespam v 0.7.3 ============================= -->\n");
// La norme HTML interdit la balise style ailleurs que dans <head></head>
// on l'utilise ici ? titre de d?bogage et pendant la construction de l'action
/*$buffer->str(	"<style type=\"text/css\">",
	"p { margin: 0; }",
	".action_erasespam { background-color: yellow; }",
	".action_erasespam td { text-align: right; vertical-align: top; }",
	"</style>\n");*/



// -- (1) Formulaire d'accueil de l'action -------------------------------
//
// Le formulaire est affich? si aucun spammer n'a encore ?t? pr?cis? ou
// si le champ a ?t? laiss? vide et valid?



if(empty($_POST['spammer']) && empty($_POST['from']) && !isset($_POST['clean']))
{
	$buffer->str(	"<div class=\"action_erasespam\">\n" .
		"<form method=\"post\" action=\"". $despam_url . "\" name=\"selection\">\n".
		"<fieldset>\n".
		"<legend>S&eacute;lection des pages</legend>\n");
	$buffer->str(	"<p>\n".
		"Toutes les modifications depuis ".
		"<select name=\"from\">\n".
		"<option selected=\"selected\" value=\"1\">depuis 1 heure</option>\n".
		"<option value=\"3\">depuis 3 heures</option>\n".
		"<option value=\"6\">depuis 6 heures</option>\n".
		"<option value=\"12\">depuis 12 heures</option>\n".
		"<option value=\"24\">depuis 24 heures</option>\n".
		"<option value=\"48\">depuis 48 heures</option>\n".
		"<option value=\"168\">depuis 1 semaine</option>\n".
		"<option value=\"336\">depuis 2 semaines</option>\n".
		"<option value=\"744\">depuis 1 mois</option>\n".
		"</select>\n".
		"<button name=\"2\" value=\"Valider\">Valider</button>\n".
		"</p>\n");
	$buffer->str(	"</fieldset>\n".
		"</form>\n".
		"</div>\n\n");
}


// -- (2) Page de r?sultats et form. de s?lection des pages ? effacer ----
//
else if(!isset($_POST['clean']))
{
	if(isset($_POST['from']) && isset($_POST['2']))
	{
		$requete =
			"select *
			from ".$wiki->config["table_prefix"]."pages
			where
			time > date_sub(now(), interval " . addslashes($_POST['from']) . " hour)
			and latest = 'Y'
			order by `time` desc";
		$title =
			"<p>Nettoyage des pages vandalis?es depuis " .
			$_POST['from'] . " heure(s)</p>\n";
	}
	//$buffer->str( $requete;
	$pagesFromSpammer = $wiki->LoadAll($requete);
	// Affichage des pages pour validation
	$buffer->str(	"<div class=\"action_erasespam\">\n");
	$buffer->str(	$title);
	$buffer->str(	"<form method=\"post\" action=\"". $despam_url . "\">\n");
	$buffer->str(	"<table>\n");
	foreach ($pagesFromSpammer as $i => $page)
	{
		$revisions=$wiki->LoadAll("select * from ".$wiki->config["table_prefix"]."pages where tag = '".mysqli_real_escape_string($wiki->dblink, ($page["tag"])."' order by time desc"); 
		$buffer->str(	"<tr>\n".
			"<td>".
			$page["tag"]. " ".
			"(". $page["time"]. ") ".
				" par ". $page['user'] . " ".
			"</td>\n");
		$buffer->str(	"<td>".
			"<input name=\"suppr[]\" value=\"" . $page["tag"] . "\" type=\"checkbox\" /> [Suppr.!]".
			"</td>\n");
		$buffer->str(	"<td>\n");
		$buffer->str("<p>");
		$buffer->str("_____________________________________________________________________________________________________");
		$buffer->str("<p>");
		
		
		
		foreach ($revisions as $revision)
		{
			// Si c'est la derni?re version on saute cette it?ration
			// ce n'est pas elle qu'on va vouloir restaurer...
			if(!isset($revision1))
			{
				$revision1 = "";
				continue;
			}
			$buffer->str(	"<input name=	\"rev[]\" value=\"" . $revision["id"] . "\" type=\"checkbox\" /> ");
			$buffer->str(	"Restaurer depuis la version du ".
				 " ".$revision["time"]." ".
				" par ". $revision['user'] . " ".
				"<br />\n");
		}
		unset($revision1);
		$buffer->str(	//" . . . . ",$wiki->Format($page["user"]),"</p>\n",
			"</td>\n",
			"</tr>\n",
			"");
	}
	$buffer->str(	"</table>\n");
	$buffer->str(	"<p>Commentaire&nbsp;: <input name=\"comment\" style=\"width: 80%;\" /></p>\n");
	$buffer->str(	"<p>\n".
		"<input type=\"hidden\" name=\"spammer\" value=\"" . $_POST['spammer'] . "\" />\n".
		"<input type=\"hidden\" name=\"clean\" value=\"yes\" />\n".
		"<button value=\"Valider\">Nettoyer >></button>\n".
		"</p>\n");
	$buffer->str(	"</form>\n");
	$buffer->str(	"</div>\n\n");
}


// -- (3) Nettoyage des pages et affichage de la page de r?sultats -------
//

else if(isset($_POST['clean']))
{

	//$buffer->str( "<script type=\"text/javascript\">alert('test');</script>";
	$deletedPages = "";
	$restoredPages = "";

	// -- 3.1 Effacement ---
	// On efface chaque ?l?ment du tableau suppr[]
	// Pour chaque page s?lectionn?e
	if (!empty($_POST['suppr']))
	{
		foreach ($_POST['suppr'] as $page)
		{
			// Effacement de la page en utilisant la m?thode ad?quate
			// (si DeleteOrphanedPage ne convient pas, soit on cr??
			// une autre, soit on la modifie
			$wiki->DeleteOrphanedPage($page);
			$deletedPages .= $page . ", ";
		}
		$deletedPages = trim($deletedPages, ", ");
	}


	// -- 3.2 Restauration des pages s?lectionn?es ---
	if (!empty($_POST['rev']))
	{
		//print_r($_POST["rev"]);
		foreach ($_POST["rev"] as $rev_id)
		{
			$buffer->str( $rev_id."<br>");
			// S?lectionne la r?vision
			$revision = $wiki->LoadSingle("select * from ".$wiki->config["table_prefix"]."pages where id = '".mysqli_real_escape_string($wiki->dblink, ($rev_id)."' limit 1"); 
			
	
			// Fait de la derni?re version de cette r?vision
			// une version archiv?e
			$requeteUpdate =
				"update " . $wiki->config["table_prefix"] . "pages " .
				"set latest = 'N' ".
				"where latest = 'Y' " .
				"and tag = '" . $revision["tag"] . "' " .
				"limit 1";
			$wiki->Query($requeteUpdate);
			$restoredPages .= $revision["tag"] . ", ";
	
             // add new revision
              $wiki->Query("insert into ".$wiki->config["table_prefix"]."pages set ".
             "tag = '".mysqli_real_escape_string($wiki->dblink, ($revision['tag'])."', ".
             "time = now(), ".
	         "owner = '".mysqli_real_escape_string($wiki->dblink, ($revision['owner'] )."', ".
             "user = '".mysqli_real_escape_string($wiki->dblink, ("despam")."', ".
             "latest = 'Y', ".
             "body = '".mysqli_real_escape_string($wiki->dblink, (chop($revision['body']))."'");
        }
	
		}
		$restoredPages = trim($restoredPages, ", ");
		
		$buffer->str( "<li>Pages restaur?es&nbsp;: " .
		$restoredPages . ".</li>\n" );
		$buffer->str( "<li>Pages supprim?es&nbsp;: " .
		$deletedPages . ".</li>\n" );
		
		$buffer->str(	"</ul>\n");
		$buffer->str(	"<p><a href=\"". $despam_url. "\">Retour au formulaire de d?part >></a></p>\n");
		$buffer->str(	"<p><a href=\"" );
	
		$buffer->str(	"</div>\n\n");

		
}



?>
