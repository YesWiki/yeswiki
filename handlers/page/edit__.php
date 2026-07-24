<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\TagsManager;

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
