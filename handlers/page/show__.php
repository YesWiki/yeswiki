<?php

use YesWiki\Core\Service\EntryManager;

// relocated from tools/bazar/handlers/page/show__.php (ticket 24): if the page is a bazar
// entry, replace the hidden aceditor "body" field with the entry's own JSON data so edits
// go through the entry form instead of the raw wikitext editor.
$entryManager = $this->services->get(EntryManager::class);

if ($entryManager->isEntry($this->GetPageTag())) {
    $this->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js', true, true);

    $fiche = $entryManager->getOne($this->GetPageTag());

    $replace = '<input type="hidden" name="body" value="' . htmlspecialchars(json_encode($fiche), ENT_COMPAT, YW_CHARSET) . '" />';
    if (isset($_GET['time'])) {
        $replace = '<input type="hidden" name="time" value="' . htmlspecialchars($_GET['time'], ENT_COMPAT, YW_CHARSET) . '">' . "\n" . $replace;
    }
    $plugin_output_new = preg_replace(
        '/\<input type=\"hidden\" name=\"body\" value=\".*\" \/\>/Uis',
        $replace,
        $plugin_output_new
    );
}

// on efface des événements javascript issus de wikini
$plugin_output_new = str_replace('ondblclick="doubleClickEdit(event);"', '', $plugin_output_new);

// on efface aussi le message sur la non-modification d'une page, car contradictoire avec le changement de theme, et inéfficace pour l'expérience utilisateur
// TODO check if the following line is really usefull
$plugin_output_new = str_replace('onload="alert(\'' . _t('EDIT_NO_CHANGE_MSG') . '\');"', '', $plugin_output_new);

if (isset($GLOBALS['template-error']) && $GLOBALS['template-error']['type'] == 'theme-not-found') {
    // on affiche le message d'erreur des templates inexistants
    $plugin_output_new = str_replace(
        '<div class="page" >',
        '<div class="page">' . "\n" . '<div class="yw-alert yw-alert--danger"><a href="#" data-yw-dismiss="alert" class="yw-close">&times;</a><strong>' . _t('TEMPLATE_NO_THEME_FILES') . ' :</strong><br />themes/' . $GLOBALS['template-error']['theme'] . '/squelettes/' . $GLOBALS['template-error']['squelette'] . '<br />themes/' . $GLOBALS['template-error']['theme'] . '/styles/' . $GLOBALS['template-error']['style'] . '<br><strong>' . _t('TEMPLATE_DEFAULT_THEME_USED') . '</strong>.</div>',
        $plugin_output_new
    );
    $GLOBALS['template-error'] = '';
}

if (!$this->HasAccess('read')) {
    if ($contenu = $this->LoadPage('PageLogin')) {
        $output = '<body class="login-body">' . "\n"
            . '<div class="container">' . "\n"
            . '<div class="yeswiki-page-widget page-widget page" ' . $this->Format('{{doubleclic iframe="1"}}') . '>' . "\n";
        $output .= '<div class="yw-alert yw-alert--danger">' .
            _t('LOGIN_NOT_AUTORIZED') . ', ' . _t('LOGIN_PLEASE_REGISTER') . '.' .
            '</div>' . "\n";
        $output .= $this->Format('{{include page="PageLogin"}}');
        $output .= '</div><!-- end .page-widget -->' . "\n";
        $output .= '</div><!-- end .container -->' . "\n";
        $output = $this->Header() . $output;
        $output .= $this->Footer();
    } else {
        // sinon on affiche le formulaire d'identification minimal
        $output = str_replace(
            '<i>' . _t('LOGIN_NOT_AUTORIZED') . '</i>', // to sync with /handlers/page/show.php
            '<div class="alert alert-danger alert-error">' .
                _t('LOGIN_NOT_AUTORIZED') . ', ' . _t('LOGIN_PLEASE_REGISTER') . '.' .
                '</div>' . "\n" .
                $this->Format('{{login context="login-page" signupurl="0"}}'),
            $plugin_output_new
        );
    }
    $this->exit($output);
}
