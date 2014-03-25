<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if (!CACHER_MOTS_CLES && $this->HasAccess("write") && $this->HasAccess("read"))
{
	$response = array();
	// on recupere tous les tags du site
	$tab_tous_les_tags = $this->GetAllTags();
	if (is_array($tab_tous_les_tags))
	{
		foreach ($tab_tous_les_tags as $tab_les_tags)
		{
			$response[] = _convert($tab_les_tags['value'], 'ISO-8859-1');
		}
	}
	sort($response);
	$tagsexistants = '\''.implode('\',\'', $response).'\'';

	
	// on recupere les tags de la page courante
	$tabtagsexistants = $this->GetAllTags($this->GetPageTag());
	foreach ($tabtagsexistants as $tab)
	{
		$tagspage[] = _convert($tab["value"], 'ISO-8859-1');
	}

	if (isset($tagspage) && is_array($tagspage))
	{
		sort($tagspage);
		$tagspagecourante = implode(',', $tagspage);
	} else {
		$tagspagecourante = '';
	}
	$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').'
	<script src="tools/tags/libs/jquery-ui-1.9.2.custom.min.js"></script>
	<script src="tools/tags/libs/tag-it.js"></script>	
	<script>
	$(function(){
        var tagsexistants = ['.$tagsexistants.'];

	    $(\'#pagetags\').tagit({
		    availableTags: tagsexistants
		});
		
		//bidouille antispam
		$(".antispam").attr(\'value\', \'1\');
	});
	</script>';
}

//Sauvegarde
if (!CACHER_MOTS_CLES && $this->HasAccess("write") && 
	isset($_POST["submit"]) && $_POST["submit"] == 'Sauver' && 
	isset($_POST["pagetags"]) && $_POST['antispam']==1 )
{
	$this->SaveTags($this->GetPageTag(), $_POST["pagetags"]);
}

// If the page is an ebook, we will display the ebook generator 
if ($this->HasAccess('write') && isset($this->page["metadatas"]["ebook-title"])) {
	//var_dump($this->page["metadatas"] ["ebook-title"]);break;
	$pageeditionebook = $this->Format('{{ebookgenerator}}');
}

?>
