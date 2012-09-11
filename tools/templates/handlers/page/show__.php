<?php
/*
*/
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

// on remplace les liens vers les NomWikis n'existant pas
$plugin_output_new = replace_missingpage_links($plugin_output_new);

// on efface des événements javascript issus de wikini, qui sont trop mals faits!! ;)
$plugin_output_new = str_replace('ondblclick="doubleClickEdit(event);"', '', $plugin_output_new );

// on efface aussi le message sur la non-modification d'une page, car contradictoire avec le changement de theme, et inéfficace pour l'expérience utilisateur
$plugin_output_new = str_replace('onload="alert(\'Cette page n\\\'a pas &eacute;t&eacute; enregistr&eacute;e car elle n\\\'a subi aucune modification.\');"', '', $plugin_output_new );
?>
