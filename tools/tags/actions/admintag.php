<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if ($this->UserIsAdmin())
{

	if (isset($_GET['supprimer_tag']))
	{		
		$sql = 'DELETE FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" and value="'.mysql_real_escape_string($_GET['supprimer_tag']).'"';
		if (isset($_GET['pagetag']))
		{
			$sql .= ' AND resource="'.$_GET['pagetag'].'"';
		}
		$this->Query($sql);
	}

	//on r?cup?re tous les tags existants
	$sql = 'SELECT value, resource FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" ORDER BY value ASC, resource ASC';
	$tab_tous_les_tags = $this->LoadAll($sql);

	if (is_array($tab_tous_les_tags))
	{
		echo '<table class="taglist">'."\n";
		$nb_pages = 0;
		$liste_page = '';
		$tag_precedent = '';
		$tab_tous_les_tags[]='fin'; //on ajoute un ?l?ment au tableau pour bloucler une derniere fois
		foreach ($tab_tous_les_tags as $tab_les_tags)
		{
			if ($tab_les_tags['value']==$tag_precedent || $tag_precedent== '')
			{
				$nb_pages++;
				if ($tag_precedent=='') $tag_precedent = $tab_les_tags['value'];
				if ($nb_pages == 1) $liste_page .= '<tr><td>nbpage</td>';
				else $liste_page .= '<tr><td></td>';
				$liste_page .= '<td class="pagewithtag">
					<a class="wikipagelink" href="'.$this->href('',$tab_les_tags['resource']).'">'.$tab_les_tags['resource'].'</a>
				</td>
				<td class="delete_tag">
					<a class="supprimer_tag" href="'.$this->href().'&amp;supprimer_tag='.urlencode($tag_precedent).'&amp;pagetag='.$tab_les_tags['resource'].'">supprimer</a>&nbsp;<span class="tagit-tag">'.$tag_precedent.'</span> de cette page.
				</td>
				</tr>'."\n";

			}
			else
			{
				//on affiche les informations pour ce tag
				$texte_liste  = '<tr>'."\n".'<td class="taglistitem">'."\n".'<span class="tagit-tag">'.$tag_precedent.'</span>'."\n";
				$texte_liste .= '<span class="tagpresence">pr&eacute;sent dans :</span>'."\n".'</td>'."\n";
				$liste_page = str_replace('<tr><td>nbpage</td>',$texte_liste, $liste_page);
				if ($nb_pages>1) {
					$liste_page .= '<tr><td></td><td></td><td class="delete_all_tags"><a class="supprimer_tag" href="'.$this->href().'&amp;supprimer_tag='.urlencode($tag_precedent).'">supprimer</a>&nbsp;<span class="tagit-tag">'.$tag_precedent.'</span> de toutes les pages.</td></tr>'."\n";
				}
				echo $liste_page.'<tr><td class="spacer" colspan="3">&nbsp;</td></tr>';

				//on r?initialise les variables
				$nb_pages = 1;
				$liste_page = '<tr><td>nbpage</td>
				<td class="pagewithtag">
					<a class="wikipagelink" href="'.$this->href('',$tab_les_tags['resource']).'">'.$tab_les_tags['resource'].'</a>
				</td>
				<td class="delete_tag">
					<a href="'.$this->href().'&amp;supprimer_tag='.urlencode($tab_les_tags['value']).'&amp;pagetag='.$tab_les_tags['resource'].'" class="supprimer_tag">supprimer</a>&nbsp;<span class="tagit-tag">'.$tab_les_tags['value'].'</span> de cette page.
				</td>
				</tr>'."\n";
			}
			$tag_precedent = $tab_les_tags['value'];
		}
		echo '</table>'."\n";
	}
}
else
{
	echo $this->Format("//L'action admintag est r&eacute;serv&eacute;e au groupe des administrateurs...//");
}

?>
