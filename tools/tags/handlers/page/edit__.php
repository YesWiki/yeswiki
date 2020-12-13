<?php
use YesWiki\Tags\Service\TagsManager;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if (!CACHER_MOTS_CLES && $this->HasAccess("write") && $this->HasAccess("read")) {
    // on recupere les tags de la page courante
    $tagsManager = $this->services->get(TagsManager::class);
    $tabtagsexistants = $tagsManager->getAll($this->GetPageTag());
    $tagspage = array_column($tabtagsexistants, 'value');
    if (!empty($tagspage)) {
        sort($tagspage);
        $tagspagecourante = implode(',', $tagspage);
    } else {
        $tagspagecourante = '';
    }
    $formtag = '
	<i class="fas fa-tags"></i> <strong>'._t('TAGS_TAGS').'</strong>
	<input class="yeswiki-input-pagetag" name="pagetags" type="text" value="'.htmlspecialchars(stripslashes($tagspagecourante)).'" placeholder="'._t('TAGS_ADD_TAGS').'">
    <input type="hidden" class="antispam" name="antispam" value="0">';
    $plugin_output_new = preg_replace(
        '/(<textarea id="body".*>([^<]*)<\/textarea>)/Ui',
        '$1'."\n".$formtag."\n",
        $plugin_output_new
    );
}
