<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Tags\Service\TagsManager;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$params = $this->services->get(ParameterBagInterface::class);
if (!$params->get('hide_keywords') && $this->HasAccess('write') && $this->HasAccess('read')) {
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
    $html = '
	<i class="fas fa-tags"></i> <strong>' . _t('TAGS_TAGS') . '</strong>
	<input class="yeswiki-input-pagetag" name="pagetags" type="text" value="' . htmlspecialchars(stripslashes($tagspagecourante)) . '" placeholder="' . _t('TAGS_ADD_TAGS') . '">
    <input type="hidden" class="antispam" name="antispam" value="0">';

    $target = '<div class="tags-container">';
    $plugin_output_new = str_replace($target, $target . $html, $plugin_output_new);
}
