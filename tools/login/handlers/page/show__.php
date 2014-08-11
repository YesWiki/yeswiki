<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}
if (!$this->HasAccess('read')) {
	//si une page PageLogin existe, on l'affiche
	if ($contenu = $this->LoadPage("PageLogin")) {
		$output = '';
		// on recupere les entetes html mais pas ce qu'il y a dans le body
		$header =  explode('<body',$this->Header());
		$output .= $header[0]."<body>\n<div class=\"container\">\n";	
		$output .= $this->Format($contenu["body"]);// on recupere juste les javascripts et la fin des balises body et html
		$output .=  preg_replace('/^.+<script/Us', $styleiframe.'<script', $this->Footer());
		$plugin_output_new = $output;
	}

	//sinon on affiche le formulaire d'identification minimal
	else {
		$plugin_output_new = str_replace ("<i>Vous n'&ecirc;tes pas autoris&eacute; &agrave; lire cette page</i>", 
											'<div class="error_box">Vous n\'&ecirc;tes pas autoris&eacute; &agrave; lire cette page, veuillez vous identifier.</div>'."\n".$this->Format('{{login template="minimal.tpl.html"}}'), $plugin_output_new);
	}
}


?>
