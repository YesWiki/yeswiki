<?php
/*
listepages.php

Copyright 2009  Florian SCHMITT
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

// recuperation de tous les parametres
$type = $this->GetParameter('type');
$tags = $this->GetParameter('tags');
$notags = $this->GetParameter('notags');
$lienedit = $this->GetParameter('edit');
$class = $this->GetParameter('class');
if (empty($class)) $class = 'liste';
$tri = $this->GetParameter('tri');
$nb = $this->GetParameter('nb');
$template = $this->GetParameter('vue');
if (empty($template)) $template = 'liste_microblog.tpl.html';
$req = '';
$req_from = '';
$req_having = '';

//on fait les tableaux pour les tags, puis on met des virgules et des guillemets
if (!empty($tags))
{
	$req_from .= ", ".$this->config["table_prefix"]."triples tags ";
	$tags=trim($tags);
	$tab_tags = explode(" ", $tags);
	$nbdetags = count($tab_tags);
	$tags = implode(",", array_filter($tab_tags, "trim"));
	$tags = '"'.str_replace(',','","',$tags).'"';
	$req .= ' AND tags.value IN ('.$tags.') ';
	$req .= ' AND tags.property="http://outils-reseaux.org/_vocabulary/tag" AND tags.resource=tag ';
	$req_having .= ' HAVING COUNT(tag)='.$nbdetags.' ';
}

if (!empty($notags))
{
	$notags=trim($notags);
	$tab_notags = explode(" ", $notags);
	$notags = implode(",", array_filter($tab_notags, "trim"));
	$notags = '"'.str_replace(',','","',$notags).'"';
	$req .= ' AND NOT EXISTS (SELECT NULL FROM '.$this->config["table_prefix"].'triples notags WHERE notags.resource = tags.resource and notags.value IN ('.$notags.') AND notags.property="http://outils-reseaux.org/_vocabulary/tag" AND notags.resource=tag ) ';
}


//traitement du type de page
if (!empty($type))
{
	$req_from .= ", ".$this->config["table_prefix"]."triples type ";
	$req .= ' AND type.resource=tag AND type.property="http://outils-reseaux.org/_vocabulary/type" AND type.value="'.$type.'" ';
}

$req .= ' GROUP BY tag ';
if ($req_having!='') $req .= $req_having;

//gestion du tri de l'affichage
if (!empty($tri))
{
	if ($tri == "alpha")
	{
		$req .= ' ORDER BY tag ASC ';
	}
	elseif ($tri == "date")
	{
		$req .= ' ORDER BY time DESC ';
	}
}
//par defaut on tri par date
else
{
		$req .= ' ORDER BY time DESC';
}

$requete = "SELECT DISTINCT tag, time, user, owner, body FROM ".$this->config["table_prefix"]."pages".$req_from." WHERE latest = 'Y' and comment_on = '' ".$req;

require_once 'tools/tags/libs/MDB2.php';
$dsn = array(
    'phptype'  => 'mysql',
    'username' => $this->config["mysql_user"],
    'password' => $this->config["mysql_password"],
    'hostspec' => $this->config["mysql_host"],
    'database' => $this->config["mysql_database"],
);

// create MDB2 instance
$db =& MDB2::connect($dsn);

if (!empty($nb))
{
	require_once 'tools/tags/libs/Pager/Pager_Wrapper.php'; //this file
	$pagerOptions = array(
    	'mode'    => 'Sliding',
   	 	'delta'   => 2,
    	'perPage' => $nb,
 	);
	$paged_data = Pager_Wrapper_MDB2($db, $requete, $pagerOptions);
	//$paged_data['page_numbers']; //array('current', 'total');
} else
{
	$paged_data['data'] = $db->queryAll($requete, null, MDB2_FETCHMODE_ASSOC);
}

$text = '';
foreach ($paged_data['data'] as $microblogpost)
{
    if (!file_exists('tools/tags/presentation/'.$template)) 
	{
		exit('Le fichier template du formulaire de microblog "tools/tags/presentation/'.$template.'" n\'existe pas. Il doit exister...');
	}
	elseif ( $this->tag!=$microblogpost['tag'] )
	{
		include_once('tools/tags/libs/squelettephp.class.php');
		$valtemplate=array();
		$squel = new SquelettePhp('tools/tags/presentation/'.$template);
		$valtemplate['class'] = $class;
		$valtemplate['lien'] = $this->href('',$microblogpost['tag']);
		$valtemplate['nompage'] = $microblogpost['tag'];
		if ($template=='liste_microblog.tpl.html')
		{		
			$squel->set($valtemplate);
			$text .= '<ul>'.$squel->analyser().'</ul>';
		}
		else 
		{
			$valtemplate['user'] = $this->Format($microblogpost["user"]);					
			$valtemplate['date'] = date("\l\e d.m.Y &\a\g\\r\av\e; H:i:s", strtotime($microblogpost["time"]));
			$valtemplate['billet'] = $this->Format($microblogpost["body"]);
			// load comments for this page
	        include_once('tools/tags/libs/tags.functions.php');
	        $valtemplate['commentaire'] = '<strong class="lien_commenter">Commentaires</strong>'."\n";
    		$valtemplate['commentaire'] .= "<div class=\"commentaires_billet_microblog\">\n";
			$valtemplate['commentaire'] .= afficher_commentaires_recursif($microblogpost['tag'], $this);
			$valtemplate['commentaire'] .= "</div>\n";
			
			//liens d'actions sur le billet			
			$valtemplate['edition'] = '<a href="'.$this->href('', $microblogpost['tag']).'" class="voir_billet">Afficher</a> ';
			if ($this->HasAccess('write', $microblogpost['tag']))
			{
				$valtemplate['edition'] .= '<a href="'.$this->href('edit', $microblogpost['tag']).'" class="editer_billet">Editer</a> ';
			}			
			if ($this->UserIsOwner($microblogpost['tag']) || $this->UserIsAdmin())
			{
				$valtemplate['edition'] .= '<a href="'.$this->href('deletepage', $microblogpost['tag']).'" class="supprimer_billet">Supprimer</a>'."\n" ;
			}				
			$squel->set($valtemplate);
			$text .= $squel->analyser();			
		}					
	} 
}

if ($vue=='accordeon')
{
	//javascript accordeon
	echo $text.'
	<script type="text/javascript">
	    <!--
	    $(document).ready( function () {
	        // On cache les pages inclues
	        $("div.include").hide();          
	        // On modifie l\'evenement "click" sur les liens vers la page
	        $("a.lien_accordeon").click( function () {
	            // Si le div etait deja ouvert, on le referme :
	            $("div.include:visible").slideUp("fast");
	            
	            // Si le div est cache, on ferme les autres et on l\'affiche :            
	            $(this).next().next("div.include").slideDown("fast");
	            
	            // On empeche le navigateur de suivre le lien :
	            return false;
	        });
	    
	    } ) ;
	    // -->
	    </script>
	
	';
}
elseif ($vue=='liste' && $text!='') echo '<ul>'.$text.'</ul>'."\n"; 
else echo $text;


//show the links
if (!empty($nb) && $paged_data['links']!='') echo "\n".'<div class="liste_pager">'."\n".$paged_data['links']."\n".'</div>'."\n";


?>
