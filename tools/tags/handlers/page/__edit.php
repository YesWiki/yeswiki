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
	$script = '$(function(){
    var tagsexistants = ['.$tagsexistants.'];
    var pagetag = $(\'#ACEditor .yeswiki-input-pagetag\');
	pagetag.tagsinput({
		typeahead: {
			source: tagsexistants
		},
		confirmKeys: [13, 188]
	});
	
	//bidouille antispam
	$(".antispam").attr(\'value\', \'1\');

	$("#ACEditor").on(\'submit\', function() {
		pagetag.tagsinput(\'add\', pagetag.tagsinput(\'input\').val());
	});
});'."\n";
  $this->AddJavascriptFile('tools/tags/libs/vendor/bootstrap-tagsinput.min.js');
  $this->AddJavascript($script);
}

//Sauvegarde
if (!CACHER_MOTS_CLES && $this->HasAccess("write") && 
	isset($_POST["submit"]) && $_POST["submit"] == 'Sauver' && 
	isset($_POST["pagetags"]) && $_POST['antispam']==1 )
{
	$this->SaveTags($this->GetPageTag(), stripslashes($_POST["pagetags"]));
}

// If the page is an ebook, we will display the ebook generator 
if ($this->HasAccess('write') && isset($this->page["metadatas"]["ebook-title"])) {
	$pageeditionebook = $this->Format('{{ebookgenerator}}');
}

?>
