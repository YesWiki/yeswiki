<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Controller\CaptchaController;
use YesWiki\Core\Service\InputFilter;
use YesWiki\Core\Service\ThemeSelectorRenderer;
use YesWiki\Core\Service\HashCashService;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Core\Service\ThemeManager;

$params = $this->services->get(ParameterBagInterface::class);
if (!$params->get('hide_keywords') && $this->HasAccess('write') && $this->HasAccess('read')) {
    // on recupere les tags de la page courante
    $tagsManager = $this->services->get(TagsManager::class);
    $tabtagsexistants = $tagsManager->getAll($this->GetPageTag());
    $tagspage = array_unique(array_column($tabtagsexistants, 'value'));
    sort($tagspage);

    $chips = '';
    foreach ($tagspage as $tag) {
        $escapedTag = htmlspecialchars(stripslashes($tag), ENT_QUOTES);
        $chips .= '<span class="yw-tag-input__chip" data-yw-tag-input-chip data-tag="' . $escapedTag . '">'
            . $escapedTag
            . '<button type="button" class="yw-tag-input__chip-remove" data-yw-tag-input-remove aria-label="' . _t('TAGS_REMOVE_TAG') . '">&times;</button>'
            . '</span>';
    }

    $searchUrl = $this->Href('', 'api/tags');
    $html = '
	<i class="fas fa-tags"></i> <strong>' . _t('TAGS_TAGS') . '</strong>
	<div class="yw-tag-input" data-yw-tag-input>' . $chips . '
		<input type="text" class="yw-input yw-tag-input__search" data-yw-tag-input-search
		       name="search" autocomplete="off" placeholder="' . _t('TAGS_ADD_TAGS') . '"
		       hx-get="' . $searchUrl . '" hx-trigger="keyup changed delay:300ms" hx-include="this" hx-vals=\'{"perpage":8}\' hx-swap="none">
		<ul class="yw-suggestions" data-yw-tag-input-suggestions hidden></ul>
		<input type="hidden" name="pagetags" data-yw-tag-input-value value="' . htmlspecialchars(implode(',', $tagspage), ENT_QUOTES) . '">
	</div>
    <input type="hidden" class="antispam" name="antispam" value="0">';

    $target = '<div class="tags-container">';
    $plugin_output_new = str_replace($target, $target . $html, $plugin_output_new);
}

if ($this->HasAccess('write') && $this->HasAccess('read')) {
    // Edition
    if (!isset($_POST['submit']) || $_POST['submit'] != InputFilter::EDIT_PAGE_SUBMIT_VALUE) {
        if ($this->config['use_hashcash']) {
            $hashCash = $this->services->get(HashCashService::class);
            $hashCashCode = $hashCash->getJavascriptCode();
            $plugin_output_new = preg_replace(
                '/\<hr class=\"hr_clear\" \/\>/',
                $hashCashCode . '<hr class="hr_clear" />',
                $plugin_output_new
            );
        }
        $this->services->get(CaptchaController::class)->renderCaptcha($plugin_output_new);
    }
}

// remove the {{template ...}} action from the page body
$plugin_output_new = preg_replace(
    '/(\\{\\{template)(.*?)(\\}\\})/is',
    '',
    $plugin_output_new
);

$themeManager = $this->services->get(ThemeManager::class);

// personnalisation graphique que dans le cas ou on est autorise
if ((!isset($this->config['hide_action_template']) or (isset($this->config['hide_action_template']) && !$this->config['hide_action_template']))
    && ($this->HasAccess('write') && $this->HasAccess('read') && (!SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME || (SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME && ($this->UserIsAdmin() || $this->UserIsOwner()))))
) {
    // graphical options : theme and background image
    $selecteur = '
<div id="graphical_options" class="yw-modal">' . "\n" .
        '  <div class="yw-modal__dialog">' . "\n" .
        '    <div class="yw-modal__content">' . "\n" .
        '      <div class="yw-modal__header">' . "\n" .
        '        <a class="yw-close" data-yw-dismiss="modal">&times;</a>' . "\n" .
        '        <h3 class="yw-modal__title">' . _t('TEMPLATE_CUSTOM_GRAPHICS') . ' ' . $this->GetPageTag() . '</h3>' . "\n" .
        '      </div>' . "\n" .
        '      <div class="yw-modal__body">' . "\n";
    $selecteur .= $this->services->get(ThemeSelectorRenderer::class)->showFormThemeSelector('edit');
    $selecteur .= '
      </div>' . "\n" .
        '      <div class="yw-modal__footer">' . "\n" .
        '        <a href="#" class="yw-btn button_cancel" data-yw-dismiss="modal">' . _t('TEMPLATE_CANCEL') . '</a>' . "\n" .
        '        <a href="#" class="yw-btn yw-btn--primary button_save" data-yw-dismiss="modal">' . _t('TEMPLATE_APPLY') . '</a>' . "\n" .
        '      </div>' . "\n" .
        '    </div>' . "\n" .
        '  </div>' . "\n" .
        '</div> <!-- /#graphical_options -->' . "\n";

    // quand le changement des valeurs du template est cache, il faut stocker les valeurs deja entrees pour ne pas retourner au template par defaut
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
    $html .= '<a class="yw-btn" data-yw-modal-target="#graphical_options">' . _t('TEMPLATE_THEME') . '</a>';
}
$plugin_output_new = str_replace($target, $target . $html, $plugin_output_new);

if (!$this->HasAccess('write')) {
    $output = '';
    // on recupere les entetes html mais pas ce qu'il y a dans le body
    $header = explode('<body', $this->Header());
    $output .= $header[0] . '<body class="login-body">' . "\n"
        . '<div class="yeswiki-page-widget page-widget page">' . "\n";
    $output .= '<div class="yw-alert yw-alert--danger">'
        . _t('LOGIN_NOT_AUTORIZED_EDIT') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.'
        . '</div><!-- end .alert -->' . "\n"
        . $this->Format('{{login signupurl="0"}}' . "\n\n")
        . '</div><!-- end .page -->' . "\n";
    // on recupere juste les javascripts et la fin des balises body et html
    $output .= preg_replace('/^.+<script/Us', '<script', $this->Footer());
    $this->exit($output);
}
