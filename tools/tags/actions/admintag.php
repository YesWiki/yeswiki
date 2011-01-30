<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if ($this->UserIsAdmin())
{

	if (isset($_GET['supprimer_tag']))
	{		
		$sql = 'DELETE FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" and value="'.mysql_escape_string($_GET['supprimer_tag']).'"';
		if (isset($_GET['pagetag']))
		{
			$sql .= ' AND resource="'.$_GET['pagetag'].'"';
		}
		$this->Query($sql);
	}

	//on récupère tous les tags existants
	$sql = 'SELECT value, resource FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" ORDER BY value ASC, resource ASC';
	$tab_tous_les_tags = $this->LoadAll($sql);

	if (is_array($tab_tous_les_tags))
	{
		echo '<ul class="taglist">'."\n";
		$nb_pages = 0;
		$liste_page = '';
		$tag_precedent = '';
		$tab_tous_les_tags[]='fin'; //on ajoute un élément au tableau pour bloucler une derniere fois
		foreach ($tab_tous_les_tags as $tab_les_tags)
		{
			if ($tab_les_tags['value']==$tag_precedent || $tag_precedent== '')
			{
				$nb_pages++;
				if ($tag_precedent=='') $tag_precedent = $tab_les_tags['value'];
				$liste_page .= '<li>
				<a href="'.$this->href('',$tab_les_tags['resource']).'" class="voir_page">'.$tab_les_tags['resource'].'</a>&nbsp;
				<a href="'.$this->href().'&amp;supprimer_tag='.urlencode($tag_precedent).'&amp;pagetag='.$tab_les_tags['resource'].'" class="supprimer_page">supprimer le mot cl&eacute; "'.$tag_precedent.'" de '.$tab_les_tags['resource'].'</a>
				</li>'."\n";

			}
			else
			{
				//on affiche les informations pour ce tag
				if ($nb_pages>1) $texte_page='ces '.$nb_pages.' pages';
				else $texte_page='la page';
				$texte_liste  = '<li class="taglistitem">'."\n".'<span class="textboxlist-bit-box">'.$tag_precedent.'</span>'."\n";
				$texte_liste .= 'présent dans '.$texte_page.' :'."\n";
				$texte_liste .= '<ul class="liste_pages_tag">'."\n".$liste_page.'</ul>'."\n";
				$texte_liste .= '<a class="supprimer_page" href="'.$this->href().'&amp;supprimer_tag='.urlencode($tag_precedent).'">Supprimer tous les mots cl&eacute;s "'.$tag_precedent.'"</a>'."\n";
				$texte_liste .= '</li>'."\n";
				echo $texte_liste;

				//on réinitialise les variables
				$nb_pages = 1;
				$liste_page = '<li>
				<a href="'.$this->href('',$tab_les_tags['resource']).'" class="voir_page">'.$tab_les_tags['resource'].'</a>&nbsp;
				<a href="'.$this->href().'&amp;supprimer_tag='.urlencode($tab_les_tags['value']).'&amp;pagetag='.$tab_les_tags['resource'].'" class="supprimer_page">supprimer le mot cl&eacute; "'.$tab_les_tags['value'].'" de '.$tab_les_tags['resource'].'</a>
				</li>'."\n";
			}
			$tag_precedent = $tab_les_tags['value'];
		}
		echo '</ul>'."\n";
	}
}
else
{
	echo $this->Format("//L'action admintag est r&eacute;serv&eacute;e au groupe des administrateurs...//");
}

?>
