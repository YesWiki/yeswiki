<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

//si une page PageLogin existe, on l'affiche
if ($contenu = $this->LoadPage("PageLogin")) {
	$plugin_output_new = str_replace ("<i>Vous n'&ecirc;tes pas autoris&eacute; &agrave; lire cette page</i>", $this->Format($contenu["body"]), $plugin_output_new);
}

//sinon on affiche le formulaire d'identification minimal
else {
	$plugin_output_new = str_replace ("<i>Vous n'&ecirc;tes pas autoris&eacute; &agrave; lire cette page</i>", 
										'<div class="error_box">Vous n\'&ecirc;tes pas autoris&eacute; &agrave; lire cette page, veuillez vous identifier.</div>'."\n".$this->Format('{{login template="minimal.tpl.html"}}'), $plugin_output_new);
}

?>
