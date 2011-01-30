<?php
if (!CACHER_MOTS_CLES) 
{
	$tabtagsexistants = $this->GetAllTags($this->GetPageTag());
	$tagspage = array();
	foreach ($tabtagsexistants as $tab)
	{
		$tagspage[] = $tab["value"];
	}
	if (count($tagspage)>0)
	{
		sort($tagspage);
		$tagsexistants = '<span class="mots_cles">Mot cl&eacute;s : </span>'."\n".'<ul class="liste_tags_en_ligne">'."\n";
		foreach ($tagspage as $tag) 
		{
			$tagsexistants .= '<li class="textboxlist-bit-box"><a href="'.$this->href('listepages',$this->GetPageTag(),'tags='.$tag).'" title="Voir toutes les pages contenant ce mot cl&eacute;">'.$tag.'</a></li>'."\n";
		}
		$tagsexistants .= '</ul>'."\n";
		echo '<div class="liste_tags">'."\n".$tagsexistants.'</div>'."\n";
	}
}
?>
