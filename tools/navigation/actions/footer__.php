<? 

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$menu_page=$this->config["menu_page"];
if (isset($menu_page) and ($menu_page!="")) {
	$plugin_output_new=preg_replace ('/<\/body>/','</td></tr></table></body>', $plugin_output_new);
}
?> 