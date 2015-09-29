<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if ($this->UserIsAdmin())
{

	if (isset($_GET['delete_tag']))
	{		
		$sql = 'DELETE FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" and id IN ('.mysqli_real_escape_string($this->dblink, $_GET['delete_tag']).')';
		$this->Query($sql);
	}

	// on recupere tous les tags existants
	$sql = 'SELECT id, value, resource FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag" ORDER BY value ASC, resource ASC';
	$tab_tous_les_tags = $this->LoadAll($sql);

	if (is_array($tab_tous_les_tags) && count($tab_tous_les_tags)>1)
	{
		echo '<table class="table table-striped table-condensed">'."\n";
		$nb_pages = 0;
		$liste_page = '';
		$tag_precedent = '';
		$tab_tous_les_tags[]='fin'; // on ajoute un element au tableau pour boucler une derniere fois
		$tagsid = '';
		foreach ($tab_tous_les_tags as $tab_les_tags)
		{
			if ($tab_les_tags != 'fin') {
				$tagstripped = _convert(stripslashes($tab_les_tags['value']), 'ISO-8859-1');
			}
			else {
				$tagstripped = 'fin';
			}
			if ( $tagstripped != 'fin' && ($tagstripped==$tag_precedent || $tag_precedent== ''))
			{
				$nb_pages++;
				if ($tag_precedent=='') $tag_precedent = $tagstripped;
				if ($nb_pages == 1) $liste_page .= '<tr><td>nbpage</td>';
				else $liste_page .= '<tr><td></td>';
				$liste_page .= '<td class="pagewithtag">
					'._t('TAGS_PRESENT_IN').' : <a class="wikipagelink" href="'.$this->href('', $tab_les_tags['resource']).'">'.$tab_les_tags['resource'].'</a>
				</td>
				<td class="delete_tag">
					<a class="btn btn-xs btn-mini btn-error btn-danger" href="'.$this->href().'&amp;delete_tag='.$tab_les_tags['id'].'"><i class="icon icon-trash icon-white"></i> '._t('TAGS_DELETE_MINUSCULE').' '._t('TAGS_FROM_THIS_PAGE').'.</a>
				</td>
				</tr>'."\n";
				$tagsid .= (($tagsid == '') ? '' : ',').$tab_les_tags['id'];
			}
			else
			{
				// on affiche les informations pour ce tag
				$texte_liste  = '<tr>'."\n".'<td class="taglistitem">'."\n".'<span class="tag-label label label-primary">'.$tag_precedent.'</span>'."\n";
				$liste_page = str_replace('<tr><td>nbpage</td>',$texte_liste, $liste_page);
				if ($nb_pages>1) {
					$liste_page .= '<tr><td></td><td></td><td class="delete_all_tags"><a class="btn btn-xs btn-mini btn-error btn-danger" href="'.$this->href().'&amp;delete_tag='.urlencode($tagsid).'"><strong><i class="icon icon-trash icon-white"></i> '._t('TAGS_DELETE_MINUSCULE').'&nbsp;<span class="tag-label label label-primary">'.$tag_precedent.'</span> '._t('TAGS_FROM_ALL_PAGES').'.</strong></a></td></tr>'."\n";
				}
				echo $liste_page.'<tr><td class="spacer" colspan="3">&nbsp;</td></tr>';

				// on reinitialise les variables
				if ($tagstripped != 'fin') {
					$nb_pages = 1;
					$tagsid = $tab_les_tags['id'];
					$liste_page = '<tr><td>nbpage</td>
					<td class="pagewithtag">
						'._t('TAGS_PRESENT_IN').' : <a class="wikipagelink" href="'.$this->href('', $tab_les_tags['resource']).'">'.$tab_les_tags['resource'].'</a>
					</td>
					<td class="delete_tag">
						<a href="'.$this->href().'&amp;delete_tag='.urlencode($tab_les_tags['id']).'" class="btn btn-xs btn-mini btn-error btn-danger"><i class="icon icon-trash icon-white"></i> '._t('TAGS_DELETE_MINUSCULE').' '._t('TAGS_FROM_THIS_PAGE').'.</a>
					</td>
					</tr>'."\n";	
				}
				
			}
			$tag_precedent = $tagstripped;
		}
		echo '</table>'."\n";
	}
}
else
{
	echo '<div class="alert alert-danger"><strong>'._t('TAGS_ACTION_ADMINTAGS').' :</strong>&nbsp;'._t('TAGS_ACTION_ADMINTAGS_ONLY_FOR_ADMINS').'...</div>'."\n";
}

?>
