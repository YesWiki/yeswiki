<?php
/*
*/
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if (!CACHER_MOTS_CLES && $this->HasAccess("write") && $this->HasAccess("read"))
{
	$formtag = '<div class="edit_tags">
            <input type="text" name="pagetags" id="pagetags" value="'.$tagspagecourante.'" />
            <input type="hidden" class="antispam" name="antispam" value="0" />
            </div>';
	$plugin_output_new = preg_replace ('/\<div class=\"form-actions\">/',
	$formtag.'<div class="form-actions">', $plugin_output_new);
}

?>
