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
	$plugin_output_new = preg_replace ('/\<input name=\"submit\" type=\"submit\" value=\"Sauver\"/',
	$formtag.'<input name="submit" type="submit" value="Sauver"', $plugin_output_new);
}

?>
