<?php
// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

//si la page est de type fiche_bazar, alors on affiche la fiche plutot que de formater en wiki
$type = $this->GetTripleValue($this->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/type', '', '');
if ($type == 'fiche_bazar') {
    $valjson = $this->page["body"];
    $tab_valeurs = json_decode($valjson, true);
    if (TEMPLATES_DEFAULT_CHARSET != 'UTF-8') $tab_valeurs = array_map('utf8_decode', $tab_valeurs);
    $plugin_output_new = preg_replace ('/<div class="yeswiki-page-widget page-widget page">.*<\/div><!-- end div.page-widget -->/Uis',
        '<div class="yeswiki-page-widget page-widget page">'."\n".baz_voir_fiche(0, $tab_valeurs)."\n".'</div><!-- end div.yeswiki-page-widget -->', $plugin_output_new);
    $this->page["body"] = '""'.baz_voir_fiche(0, $tab_valeurs).'""';
}
