<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$this->AddJavascriptFile('tools/tags/libs/tag.js');

$class = $this->GetParameter('class');
if (empty($class)) $class='';
else $class = ' '.$class;

$selectiontags = '';
$tags = $this->GetParameter('tags');
if (!empty($tags))
{
	$tags = trim($tags);
	$tab_tags = explode(" ", $tags);
	$tags = implode(",", array_filter($tab_tags, "trim"));
	$tags = '"'.str_replace(',','","',$tags).'"';
	$selectiontags = ' AND value IN ('.$tags.')';
}

// définit le nombre de classes CSS disponibles pour le nuage
$nb_taille_tag = $this->GetParameter('nbclasses');
if (empty($nb_taille_tag)) $nb_taille_tag = 6;

// on récupère le nb maximum et le nb minimum d'occurences
$sql = 'SELECT COUNT(value) AS nb FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" '.$selectiontags.' GROUP BY value';
$min_max = $this->LoadAll($sql);
$min = 100000000;
$max = 0;
foreach ($min_max as $tab_min_max)
{
	if ($tab_min_max['nb'] > $max)
	{
		$max=$tab_min_max['nb'];
	}
	elseif ($tab_min_max['nb'] < $min)
	{
		$min=$tab_min_max['nb'];
	}
}
// permettra de fixer une classe pour la taille du tag
$mult = $max/$nb_taille_tag;
if ($mult<1) $mult = 1;

// on récupère tous les tags existants
$sql = 'SELECT value, resource FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" '.$selectiontags.' ORDER BY value ASC, resource ASC';
$tab_tous_les_tags = $this->LoadAll($sql);

if (is_array($tab_tous_les_tags))
{	
	$i=1;$nb_pages=0;
	$liste_page = '';
	$tag_precedent = '';
	$tab_tag = array();
	$tab_tous_les_tags['dummy']['value']='fin'; //on ajoute un element au tableau pour boucler une derniere fois
	$tab_tous_les_tags['dummy']['resource']='fin'; 
	foreach ($tab_tous_les_tags as $tab_les_tags) {
		$tagstripped = _convert(stripslashes($tab_les_tags['value']), 'ISO-8859-1');
		if ($tagstripped==$tag_precedent || $tag_precedent== '')
		{
			$nb_pages++;
			$liste_page .= '<li class="pagewiki-link"><a class="link_pagewiki" href="'.$this->href('',$tab_les_tags['resource']).'">'.$tab_les_tags['resource'].'</a></li>';
		}
		else
		{
			// on affiche les informations pour ce tag
			if ($nb_pages>1) $texte_page= $nb_pages.' '._t('TAGS_PAGES');
			else $texte_page= _t('TAGS_ONE_PAGE');
			$texte_liste  = '<li class="tag-list">'."\n".'<a class="tag-link size'.ceil($nb_pages/$mult).'" id="j'.$i.'" data-title="'.htmlspecialchars('<button class="btn-close-popover pull-right close" type="button">&times;</button>'.$texte_page.' '._t('TAGS_CONTAINING_TAG').' : <a href="'.$this->href('listpages',$this->GetPageTag(),'tags='.$tag_precedent, ENT_QUOTES, $this->config['charset']).'" class="tag-label label label-primary">'.$tag_precedent.'</a>').'" data-content="'.htmlspecialchars('<ul class="unstyled list-unstyled">'.$liste_page.'</ul>', ENT_QUOTES, $this->config['charset']).'">'.$tag_precedent.'</a>'."\n";
			$texte_liste .= '</li>'."\n";
			$tab_tag[] = $texte_liste;

			// on reinitialise les variables
			$nb_pages = 1;
			$liste_page = '<li><a class="pagewiki-link" href="'.$this->href('',$tab_les_tags['resource']).'">'.$tab_les_tags['resource'].'</a></li>'."\n";
			$i++;
		}
		$tag_precedent = $tagstripped;
	}

	if (count($tab_tag)>0)
	{
		echo '<div class="no-dblclick boite_nuage'.$class.'">
		<ul class="nuage">'."\n";
		// on regarde s'il faut trier alphabetiquement
		$tri = $this->GetParameter('tri');
		if (!empty($tri) && $tri=="alpha")
		{
		}
		else
		{
			shuffle($tab_tag);
		}
		foreach ($tab_tag as $tag) {
			echo $tag;
		}
		echo '</ul><div class="clear"></div>'."\n".'</div>'."\n";
	}
}

?>
