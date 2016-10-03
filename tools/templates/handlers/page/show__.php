<?php
/*
*/
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// on remplace les liens vers les NomWikis n'existant pas
$plugin_output_new = replace_missingpage_links($plugin_output_new);

// on efface des événements javascript issus de wikini
$plugin_output_new = str_replace('ondblclick="doubleClickEdit(event);"', '', $plugin_output_new);

// on efface aussi le message sur la non-modification d'une page, car contradictoire avec le changement de theme, et inéfficace pour l'expérience utilisateur
$plugin_output_new = str_replace('onload="alert(\'Cette page n\\\'a pas &eacute;t&eacute; enregistr&eacute;e car elle n\\\'a subi aucune modification.\');"', '', $plugin_output_new);

if (isset($GLOBALS['template-error']) && $GLOBALS['template-error']['type'] == 'theme-not-found') {
    // on affiche le message d'erreur des templates inexistants
    $plugin_output_new = str_replace(
        '<div class="page" >',
        '<div class="page">'."\n".'<div class="alert"><a href="#" data-dismiss="alert" class="close">&times;</a><strong>'._t('TEMPLATE_NO_THEME_FILES').' :</strong><br />themes/'.$GLOBALS['template-error']['theme'].'/squelettes/'.$GLOBALS['template-error']['squelette'].'<br />themes/'.$GLOBALS['template-error']['theme'].'/styles/'.$GLOBALS['template-error']['style'].'<br><strong>'._t('TEMPLATE_DEFAULT_THEME_USED').'</strong>.</div>',
        $plugin_output_new
    );
    $GLOBALS['template-error'] = '';
}

// TODO : make it work with big buffers
//$plugin_output_new = postFormat($plugin_output_new);
