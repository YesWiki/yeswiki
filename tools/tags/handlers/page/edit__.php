<?php
/*
*/
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if (!CACHER_MOTS_CLES && $this->HasAccess("write") && $this->HasAccess("read"))
{
	//on récupère les tags de la page courante
	$tabtagsexistants = $this->GetAllTags($this->GetPageTag());
	foreach ($tabtagsexistants as $tab)
	{
		$tagspage[] = $tab["value"];
	}
	$tags_javascript = '';
	$tagsexistants = '';
	if (is_array($tagspage))
	{
		sort($tagspage);
		foreach ($tagspage as $tag) 
		{
			$tags_javascript .= 't.add(\''.$tag.'\');'."\n";
		}
		$tagsexistants = implode(',',$tagspage);
	}

	$formtag = '<script src="tools/tags/libs/GrowingInput.js" type="text/javascript" charset="utf-8"></script>
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
		'.$tags_javascript.'		
	});
	</script>
			<div class="form_tags_edit">
			<label class="mots_cles" for="tags">Mots cl&eacute;s : </label>
            <input class="microblog_toustags" type="text" name="tags" value="'.$tagsexistants.'" />
            </div>
	';
	$plugin_output_new=preg_replace ('/\<input name=\"submit\" type=\"submit\" value=\"Sauver\"/',
	$formtag.'<input type="hidden" class="antispam" name="antispam" value="0" /><input name="submit" type="submit" value="Sauver"', $plugin_output_new);
}

?>
