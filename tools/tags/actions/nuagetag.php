<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').'<script defer type="text/javascript" src="tools/tags/libs/tag.js"></script>';

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
	$tab_tous_les_tags[]='fin'; //on ajoute un élément au tableau pour bloucler une derniere fois
	foreach ($tab_tous_les_tags as $tab_les_tags)
	{
		if ($tab_les_tags['value']==$tag_precedent || $tag_precedent== '')
		{
			$nb_pages++;
			$liste_page .= '<li class="liste_pageswiki"><a class="link_pagewiki" href="'.$this->href('',$tab_les_tags['resource']).'">'.$tab_les_tags['resource'].'</a></li>'."\n";

		}
		else
		{
			// on affiche les informations pour ce tag
			if ($nb_pages>1) $texte_page= $nb_pages.' pages';
			else $texte_page='Une page';
			$texte_liste  = '<li class="liste_tooltip">'."\n".'<a class="tooltip_link size'.ceil($nb_pages/$mult).'" href="'.$this->href('listpages',$this->GetPageTag(),'tags='.$tag_precedent).'" id="j'.$i.'">'.$tag_precedent.'</a>'."\n";
			$texte_liste .= '<div class="hovertip" id="tooltipj'.$i.'">'."\n".'<span class="texte_pages_assoc">'.$texte_page.' avec le mot cl&eacute; "'.$tag_precedent.'" :</span>'."\n".'<ul>'."\n";
			$texte_liste .= $liste_page."\n";
			$texte_liste .= '</ul>'."\n".'</div>'."\n";
			$texte_liste .= '</li>'."\n";
			$tab_tag[] = $texte_liste;

			// on réinitialise les variables
			$nb_pages = 1;
			$liste_page = '<li class="liste_pageswiki"><a class="link_pagewiki" href="'.$this->href('',$tab_les_tags['resource']).'">'.$tab_les_tags['resource'].'</a></li>'."\n";
			$i++;
		}
		$tag_precedent = $tab_les_tags['value'];
	}

	if (is_array($tab_tag))
	{
		echo '<div class="boite_nuage'.$class.'">
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
