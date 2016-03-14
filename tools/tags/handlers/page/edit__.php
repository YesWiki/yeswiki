<?php
/*
*/
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if (!CACHER_MOTS_CLES && $this->HasAccess("write") && $this->HasAccess("read"))
{
	$formtag = '
	<i class="icon-tags"></i> 
	<input class="yeswiki-input-pagetag" name="pagetags" type="text" value="'.htmlspecialchars(stripslashes($tagspagecourante), ENT_COMPAT | ENT_HTML401, YW_CHARSET).'" placeholder="'._t('TAGS_ADD_TAGS').'">
    <input type="hidden" class="antispam" name="antispam" value="0">';
	$plugin_output_new = preg_replace ('/\<div class=\"form-actions\">/',
	$formtag.'<div class="form-actions">', $plugin_output_new);
}

// If the page is an ebook, we will display the ebook generator 
if ($this->HasAccess('write') && isset($pageeditionebook)) {
	$plugin_output_new = preg_replace ('/(<div class="page">.*<hr class="hr_clear" \/>)/Uis',
    '<div class="page">'."\n".$pageeditionebook."\n".'<hr class="hr_clear" />', $plugin_output_new);
}

?>
