<?php
error_reporting(E_ALL);
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

include_once "tools/tags/libs/tags.functions.php";

//mot clés utilisés d'office
$tags = $this->GetParameter('tags');
$tagsvirgule = implode(',', split_words($tags));

$titrerss = $this->GetParameter('titrerss');

//classe CSS associée au formulaire
$class = $this->GetParameter('class');
if (empty($class)) $class = '';

//classe CSS associée au formulaire
$textareaclass = $this->GetParameter('textareaclass');
if (empty($textareaclass)) $textareaclass = '';

//template billets microblog
$templatepages = $this->GetParameter('templatepages');

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

if (isset($_POST['action']) && $_POST['action'] == 'save') {
	if ($_POST['antispam']==1) {
		if ($_POST['microblog_billet'] != '')
		{
			$date = date("Ymdhis");
			$this->SavePage($this->getPageTag().$date, $_POST['microblog_billet']);
			$this->InsertTriple($this->getPageTag().$date, 'http://outils-reseaux.org/_vocabulary/tag', 'microblog', '', '');
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
		$html_rss .= $this->Format('{{rss type="microblog" tags="'.$tags.'" titrerss="'.$titrerss.'"}}');
		$html_rss .= '</div>';

		// affichage du formulaire
		$html_formulaire = '';
	

		if (!file_exists('tools/tags/presentation/templates/'.$template_formulaire))
		{
			exit('Le fichier template du formulaire de microblog "tools/tags/presentation/templates/'.$template_formulaire.'" n\'existe pas. Il doit exister...');
		}
		else
		{
			include_once('tools/tags/libs/squelettephp.class.php');
			$squel = new SquelettePhp('tools/tags/presentation/templates/'.$template_formulaire);
			//pour la veille,
			if(!empty($_GET['microblog'])) {
				$texte_billet = trim(urldecode($_GET['microblog']));
			} 
			else {
				$texte_billet = '';
			}
			$squel->set(array("nb"=>$nbcar, "rss"=>$html_rss, "billet"=>$texte_billet, "class"=>$class, "textareaclass"=>$textareaclass, "url"=>$this->href(), "pagetag"=>$this->GetPageTag()));
			$html_formulaire .= $squel->analyser();
		}

		//on récupère tous les tags existants
		/*$tab_tous_les_tags = $this->GetAllTags();
		$toustags = '';
		if (is_array($tab_tous_les_tags))
		{
			foreach ($tab_tous_les_tags as $tab_les_tags)
			{
				$toustags .= $tab_les_tags['value'].' ';
			}
			$toustags = substr($toustags,0,-1);
		}
		$tous_les_tags = explode(' ', $toustags);
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
	    </script>'."\n";*/
	    $GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').'	<script type="text/javascript" src="tools/tags/libs/microblog.js"></script>'."\n";


		//on formatte l'action includetag qui va tout nous afficher à l'écran la liste des bulles du microblog
		$texte = '{{listepages type="microblog" tags="'.$tags.'" tri="'.$tri.'"';
		if (!empty($templatepages))  $texte .= ' vue="'.$templatepages.'"';
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
