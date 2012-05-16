<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

include_once "tools/tags/libs/tags.functions.php";

//mot clés utilisés d'office
$tags = $this->GetParameter('tags');
$tagsvirgule = implode(',', split_words($tags));

//mot clés cachés d'office
$notags = $this->GetParameter('notags');

//peut on éditer les pages?
$lienedit = $this->GetParameter('edit');

$titrerss = $this->GetParameter('titrerss');

//classe CSS associée
$class = $this->GetParameter('class');
if (empty($class)) $class = 'microblog';

//template billets microblog
$vue = $this->GetParameter('vue');
if (empty($vue)) $vue = 'bulle_microblog.tpl.html';

//formulaire de microblog au dessus ou en dessous
$enhaut = $this->GetParameter('enhaut');
if (empty($enhaut)) $enhaut="oui";

//formulaire microblog seul ou avec la liste des billets?
$afficher = $this->GetParameter('afficher');
if (empty($afficher)) $afficher="liste";

//tri alphabetique ou par date
$tri = $this->GetParameter('tri');
if (empty($tri)) $tri = 'date';

//nom du template de formulaire
$template_formulaire = $this->GetParameter('template');
if (empty($template_formulaire)) $template_formulaire = "formulaire_microblog_simple.tpl.html";

//nombre de pages wiki affichées par page
$nb = $this->GetParameter('nb');

//nombre de caracteres maximum pour un microbillet
$nbcar = $this->GetParameter('nbcar');
if (empty($nbcar)) $nbcar=300;

if (isset($_POST['FormMicroblog'])) {
	if ($_POST['antispam']==1) {
		if ($_POST['microblog_billet'] != '')
		{
			$date = date("Ymdhis");
			$this->SavePage($this->getPageTag().$date, $_POST['microblog_billet']);
			$this->InsertTriple($this->getPageTag().$date, 'http://outils-reseaux.org/_vocabulary/type', 'microblog', '', '');
			$this->SaveTags($this->getPageTag().$date, $tagsvirgule.','.$_POST['microblog_tags']);
		}
		$this->Redirect($this->Href());
		exit;
	} else {
		$this->SetMessage("Il faut avoir javascript d'activ&eacute; pour &eacute;crire des billets.");
		$this->Redirect($this->Href());
		exit;
	}
}

else {
	if ($this->GetMethod() != 'xml')
	{
		//on affiche le lien vers le flux RSS
		$html_rss = '<div class="liens_rss">';
		$html_rss .= $this->Format('{{rss type="microblog" tags="'.$tags.'" notags="'.$notags.'" titrerss="'.$titrerss.'"}}');
		$html_rss .= '</div>';

		// affichage du formulaire
		$html_formulaire = '';
		$html_formulaire .= $this->FormOpen();
		$html_formulaire .= '<input type="hidden" name="FormMicroblog" value="true" />'."\n";
		$html_formulaire .= '<input type="hidden" class="antispam" name="antispam" value="0" />'."\n";

		if (!file_exists('tools/tags/presentation/'.$template_formulaire))
		{
			exit('Le fichier template du formulaire de microblog "tools/tags/presentation/'.$template_formulaire.'" n\'existe pas. Il doit exister...');
		}
		else
		{
			include_once('tools/tags/libs/squelettephp.class.php');
			$squel = new SquelettePhp('tools/tags/presentation/'.$template_formulaire);
			//pour la veille,
			if(!empty($_GET['microblog'])) $texte_billet=trim(urldecode($_GET['microblog']));
			$squel->set(array("nb"=>$nbcar, "rss"=>$html_rss, "billet"=>$texte_billet));
			$html_formulaire .= $squel->analyser();
		}

		//on récupère tous les tags existants
		$tab_tous_les_tags = $this->GetAllTags();
		$toustags = '';
		if (is_array($tab_tous_les_tags))
		{
			foreach ($tab_tous_les_tags as $tab_les_tags)
			{
				$toustags .= $tab_les_tags['value'].' ';
			}
			$toustags = substr($toustags,0,-1);
		}
		$tous_les_tags = split(' ', $toustags);
		$html_formulaire .= '<script src="tools/tags/libs/GrowingInput.js" type="text/javascript" charset="utf-8"></script>
		<script src="tools/tags/libs/tags_suggestions.js" type="text/javascript" charset="utf-8"></script>
		<script type="text/javascript">
			$(document).ready(function() {
				// Autocomplétion des mot-clés	
				var t = new $.TextboxList(\'.microblog_toustags\', {unique: true, plugins: {autocomplete: {}}});
				t.getContainer().addClass(\'textboxlist-loading\');				
				$.ajax({url: \''.$this->href('json',$this->GetPageTag()).'\', dataType: \'json\', success: function(r){
					t.plugins[\'autocomplete\'].setValues(r);
					t.getContainer().removeClass(\'textboxlist-loading\');
				}});	
			});
	    </script>'."\n";
		$html_formulaire .= $this->FormClose();
		$html_formulaire .= '<br class="alaligne" />'."\n";

		//on formatte l'action includetag qui va tout nous afficher à l'écran la liste des bulles du microblog
		$texte = '{{listepages type="microblog" tags="'.$tags.'" notags="'.$notags.'" class="'.$class.'" vue="'.$vue.'" tri="'.$tri.'"';
		if (!empty($lienedit)) $texte .= ' edit="'.$lienedit.'"';
		if (!empty($nb)) $texte .= ' nb="'.$nb.'"';
		$texte .= '}}';

		//le formulaire de saisie doit il etre en haut
		if ($afficher=='liste')
		{
			if ($enhaut=='oui')
			{
				echo $html_formulaire.$this->Format($texte);
			}
			else
			{
				echo $this->Format($texte).$html_formulaire;
			}
		}
		else
		{
			echo $html_formulaire;
		}
	}
	else {
		if (empty($titrerss)) echo $this->Format('{{rss type="microblog" tags="'.$tags.'"}}');
		else echo $this->Format('{{rss type="microblog" tags="'.$tags.'" titrerss="'.$titrerss.'"}}');
	}
}
?>
