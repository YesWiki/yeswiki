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
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

// recuperation de tous les parametres
if (isset($_GET['tags'])) $tags = $_GET['tags'];
if (isset($_GET['type'])) $tags = $_GET['type'];
if (isset($_GET['lienedit'])) $lienedit = $_GET['lienedit'];
if (isset($_GET['class'])) $class = $_GET['class']; else $class = 'liste';
if (isset($_GET['nb'])) $nb = $_GET['nb'];
if (isset($_GET['tri'])) $tri = $_GET['tri']; else $tri = 'alpha';
if (isset($_GET['template'])) $template = $_GET['template']; else $template = 'accordeon_microblog.tpl.html';

$resultat = $this->PageList($tags,$type,$nb,$tri,$template,$class,$lienedit);

$content = '<h1>Liste des pages</h1>'."\n";
if ($nb_total > 1) $content .= '<div class="resultats_listepages">Un total de '.$nb_total.' pages ont &eacute;t&eacute; trouv&eacute;es.</div>'."\n";
elseif ($nb_total == 1) $content .= '<div class="resultats_listepages">Une page a &eacute;t&eacute; trouv&eacute;e.</div>'."\n";
else $content .= '<div class="resultats_listepages">Aucune page trouv&eacute;e.</div>'."\n";

$text = '';
foreach ($resultat['data'] as $microblogpost)
{
    if (!file_exists('tools/tags/presentation/'.$template)) 
	{
		exit('Le fichier template du formulaire de microblog "tools/tags/presentation/'.$template.'" n\'existe pas. Il doit exister...');
	}
	else
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

if ($template == 'accordeon_microblog.tpl.html')
{
	//javascript accordeon
	$content .= $text.'
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
elseif ($template=='liste_microblog.tpl.html' && $text!='') $content .= '<ul>'.$text.'</ul>'."\n"; 
else $content .= $text;

//pager (limite de nombre de pages visibles)
if (!empty($nb) && $resultat['links']!='') $content .= "\n".'<div class="liste_pager">'."\n".$resultat['links']."\n".'</div>'."\n";

echo $this->Header();
echo $content;
echo $this->Footer();
?>
