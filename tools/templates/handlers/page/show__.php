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
        '<div class="page">'."\n".'<div class="alert alert-danger"><a href="#" data-dismiss="alert" class="close">&times;</a><strong>'._t('TEMPLATE_NO_THEME_FILES').' :</strong><br />themes/'.$GLOBALS['template-error']['theme'].'/squelettes/'.$GLOBALS['template-error']['squelette'].'<br />themes/'.$GLOBALS['template-error']['theme'].'/styles/'.$GLOBALS['template-error']['style'].'<br><strong>'._t('TEMPLATE_DEFAULT_THEME_USED').'</strong>.</div>',
        $plugin_output_new
    );
    $GLOBALS['template-error'] = '';
}

if (!$this->HasAccess('read')) {
    if ($contenu = $this->LoadPage("PageLogin")) {
        $output = $this->Header();
        $output .= '<body class="login-body">'."\n"
            .'<div class="container">'."\n"
            .'<div class="yeswiki-page-widget page-widget page" '.$this->Format('{{doubleclic iframe="1"}}').'>'."\n";
        $output .= '<div class="alert alert-danger alert-error">'.
            _t('LOGIN_NOT_AUTORIZED').', '._t('LOGIN_PLEASE_REGISTER').'.'.
            '</div>'."\n";
        $output .= $this->Format($contenu["body"]);
        $output .= '</div><!-- end .page-widget -->' . "\n";
        $output .= '</div><!-- end .container -->' . "\n";
        $output .= $this->Footer();
    } else {
        // sinon on affiche le formulaire d'identification minimal
        $output = str_replace(
            "<i>"._t('LOGIN_NOT_AUTORIZED')."</i>",
            '<div class="alert alert-danger alert-error">'.
            _t('LOGIN_NOT_AUTORIZED').', '._t('LOGIN_PLEASE_REGISTER').'.'.
            '</div>'."\n".
            $this->Format('{{login signupurl="0"}}'),
            $plugin_output_new
        );
    }
    // common footer for all iframe page
    exit($output);
}

// TODO : make it work with big buffers
//$plugin_output_new = postFormat($plugin_output_new);
