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
if (empty($template)) $template = 'bulle_microblog.tpl.html';
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



$paged_data['data'] = $this->LoadAll($requete);
$text = '';
if (count($paged_data['data'])>0) {
	foreach ($paged_data['data'] as $microblogpost)
	{
	    if (!file_exists('tools/tags/presentation/templates/'.$template)) 
		{
			exit('Le fichier template d\'affichage de la listes des pages "tools/tags/presentation/templates/'.$template.'" n\'existe pas. Il doit exister...');
		}
		elseif ( $this->tag!=$microblogpost['tag'] )
		{
			include_once('tools/tags/libs/squelettephp.class.php');
			$valtemplate=array();
			$squel = new SquelettePhp('tools/tags/presentation/templates/'.$template);
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
				$valtemplate['tag'] = $microblogpost['tag'];
				$valtemplate['page'] = $this->Format($microblogpost["body"]);

				// load comments for this page
		        $valtemplate['comment'] = '<strong class="lien_commenter">Commentaires</strong>'."\n";
	    		$valtemplate['comment'] .= "<div class=\"commentaires_billet_microblog\">\n";
	    		include_once('tools/tags/libs/tags.functions.php');
				$valtemplate['comment'] .= afficher_commentaires_recursif($microblogpost['tag'], $this);
				$valtemplate['comment'] .= "</div>\n";
				
				//liens d'actions sur le billet	
				$valtemplate['actions'] = $this->Format('{{barreredaction page="'.$microblogpost['tag'].'"}}');	
				/*$valtemplate['edition'] = '<a href="'.$this->href('', $microblogpost['tag']).'" class="voir_billet">Afficher</a> ';
				if ($this->HasAccess('write', $microblogpost['tag']))
				{
					$valtemplate['edition'] .= '<a href="'.$this->href('edit', $microblogpost['tag']).'" class="editer_billet">Editer</a> ';
				}			
				if ($this->UserIsOwner($microblogpost['tag']) || $this->UserIsAdmin())
				{
					$valtemplate['edition'] .= '<a href="'.$this->href('deletepage', $microblogpost['tag']).'" class="supprimer_billet">Supprimer</a>'."\n" ;
				}				*/
				$squel->set($valtemplate);
				$text .= $squel->analyser();			
			}					
		} 
	}
}
else {
	$text .= '<div class="alert alert-info">
        <a data-dismiss="alert" class="close">&times;</a>
        Il n\'y a pas encore de pages cr&eacute;&eacute;es.
      </div>';

}
echo $text;


//show the links
if (!empty($nb) && $paged_data['links']!='') echo "\n".'<div class="liste_pager">'."\n".$paged_data['links']."\n".'</div>'."\n";


?>
