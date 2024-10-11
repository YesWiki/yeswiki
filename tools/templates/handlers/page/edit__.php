<?php

use YesWiki\Core\Service\ThemeManager;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}
// on enleve l'action template
$plugin_output_new = preg_replace(
    '/' . '(\\{\\{template)' . '(.*?)' . '(\\}\\})' . '/is',
    '',
    $plugin_output_new
);

$themeManager = $this->services->get(ThemeManager::class);

// personnalisation graphique que dans le cas ou on est autorise
if ((!isset($this->config['hide_action_template']) or (isset($this->config['hide_action_template']) && !$this->config['hide_action_template'])) &&
    ($this->HasAccess('write') && $this->HasAccess('read') && (!SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME || (SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME && ($this->UserIsAdmin() || $this->UserIsOwner()))))) {
    // graphical options : theme and background image
    $selecteur = '
<div id="graphical_options" class="modal fade">' . "\n" .
    '  <div class="modal-dialog">' . "\n" .
    '    <div class="modal-content">' . "\n" .
    '      <div class="modal-header">' . "\n" .
    '        <a class="close" data-dismiss="modal">&times;</a>' . "\n" .
    '        <h3>' . _t('TEMPLATE_CUSTOM_GRAPHICS') . ' ' . $this->GetPageTag() . '</h3>' . "\n" .
    '      </div>' . "\n" .
    '      <div class="modal-body">' . "\n";
    $selecteur .= $this->services->get(\YesWiki\Templates\Controller\ThemeController::class)->showFormThemeSelector('edit');
    $selecteur .= '
      </div>' . "\n" .
      '      <div class="modal-footer">' . "\n" .
      '        <a href="#" class="btn btn-default button_cancel" data-dismiss="modal">' . _t('TEMPLATE_CANCEL') . '</a>' . "\n" .
      '        <a href="#" class="btn btn-primary button_save" data-dismiss="modal">' . _t('TEMPLATE_APPLY') . '</a>' . "\n" .
      '      </div>' . "\n" .
      '    </div>' . "\n" .
      '  </div>' . "\n" .
      '</div> <!-- /#graphical_options -->' . "\n";

    //quand le changement des valeurs du template est cache, il faut stocker les valeurs deja entrees pour ne pas retourner au template par defaut
    $selecteur .= '<input id="hiddentheme" type="hidden" name="theme" value="' . $themeManager->getFavoriteTheme() . '" />' . "\n";
    $selecteur .= '<input id="hiddensquelette" type="hidden" name="squelette" value="' . $themeManager->getFavoriteSquelette() . '" />' . "\n";
    $selecteur .= '<input id="hiddenstyle" type="hidden" name="style" value="' . $themeManager->getFavoriteStyle() . '" />' . "\n";
    $selecteur .= '<input id="hiddenbgimg" type="hidden" name="bgimg" value="' . $themeManager->getFavoriteBackgroundImage() . '" />' . "\n";

    // on rajoute la personnalisation graphique
    $plugin_output_new = preg_replace('/<\/body>/', $selecteur . "\n" . '</body>', $plugin_output_new);
    $changetheme = true;
} else {
    $changetheme = false;
}

$hidden = '';
// cas des pages speciales
if (isset($_SERVER['HTTP_REFERER'])) {
    $pagetag = str_replace($this->config['base_url'], '', $_SERVER['HTTP_REFERER']);
    if ($this->IsWikiName($pagetag) && in_array(
        $pagetag,
        ['PageFooter', 'PageHeader', 'PageTitre', 'PageRapideHaut', 'PageMenuHaut', 'PageMenu']
    )) {
        $hidden = '<input type="hidden" name="returnto" value="' . $this->href('', $pagetag) . '" />' . "\n";
    }
}

$html = $hidden;
$target = '<span class="theme-container">';
if ($changetheme) {
    // Adds change theme button
    $html .= '<a class="btn btn-neutral" data-toggle="modal" data-target="#graphical_options">' . _t('TEMPLATE_THEME') . '</a>';
}
$plugin_output_new = str_replace($target, $target . $html, $plugin_output_new);

if (!$this->HasAccess('write')) {
    $output = '';
    // on recupere les entetes html mais pas ce qu'il y a dans le body
    $header = explode('<body', $this->Header());
    $output .= $header[0] . '<body class="login-body">' . "\n"
        . '<div class="yeswiki-page-widget page-widget page">' . "\n";
    $output .= '<div class="alert alert-danger alert-error">'
    . _t('LOGIN_NOT_AUTORIZED_EDIT') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.'
    . '</div><!-- end .alert -->' . "\n"
    . $this->Format('{{login signupurl="0"}}' . "\n\n")
    . '</div><!-- end .page -->' . "\n";
    // on recupere juste les javascripts et la fin des balises body et html
    $output .= preg_replace('/^.+<script/Us', '<script', $this->Footer());
    $this->exit($output);
}
