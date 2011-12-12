<?php
if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

$class = $this->GetParameter('class');

$output = '<form action="'.$this->href("show","RechercheTexte").'" method="get" class="search-form">
	<input name="wiki" value="RechercheTexte" type="hidden" />
	<input name="phrase" tabindex="1" accesskey="C" title="Rechercher dans YesWiki [alt-shift-C]" class="search_input" value="';
$output .= (isset($_POST['phrase'])) ? $_POST['phrase'] : "Recherche...";
$output .= '" onblur="if (this.value == \'\') {this.value = \'Recherche...\';}" onfocus="if (this.value==\'Recherche...\') {this.value=\'\';}" size="8" />
	<button title="Rechercher les pages comportant ce texte." name="button" type="submit" class="search_button">
		<img alt="GO" src="tools/templates/presentation/images/search-button.png">
	</button>
</form>';

echo ((!empty($class)) ? '<div class="'.$class.'">'."\n".$output."\n".'</div>' : $output);

?>
