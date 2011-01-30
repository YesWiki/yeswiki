<?php 

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}
$wikini_page_url = $this->Link($this->tag, "plugin", "Extensions");

$plugin_output_new=preg_replace ('/-- Fonctionne avec/',$wikini_page_url.' :: -- Fonctionne avec', $plugin_output_new);
?> 