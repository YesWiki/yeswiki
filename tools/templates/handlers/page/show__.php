<?php
/*
*/
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

include_once 'tools/templates/libs/templates.functions.php';

// on remplace les liens vers les NomWikis n'existant pas
$plugin_output_new = replace_missingpage_links($plugin_output_new);

// on efface des événements javascript issus de wikini, qui sont trop mals faits!! ;)
$plugin_output_new = str_replace('ondblclick="doubleClickEdit(event);"', '', $plugin_output_new );
?>
