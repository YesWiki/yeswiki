<?php

use YesWiki\Tags\Service\TagsManager;

// fonctions a inclure
include_once YESWIKI_SOURCE_DIR . '/tools/tags/libs/tags.functions.php';

$tagsManager = $this->services->get(TagsManager::class);

// recuperation de tous les parametres
$tags = filter_input(INPUT_GET, 'tags', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$lienedit = filter_input(INPUT_GET, 'lienedit', FILTER_SANITIZE_URL) ?: '';
$get_class = filter_input(INPUT_GET, 'class', FILTER_SANITIZE_SPECIAL_CHARS);
$class = !empty($get_class) ? $get_class : 'liste';
$nb = filter_input(INPUT_GET, 'nb', FILTER_SANITIZE_NUMBER_INT) ?: '';
$tri = filter_input(INPUT_GET, 'tri', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$template = basename(filter_input(INPUT_GET, 'template', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'pages_accordion.tpl.html');
$nbcartrunc = 200;
$valtemplate = [];

$output = '';

// creation de la liste des mots cles a filtrer
$this->AddJavascriptFile('tools/tags/libs/tag.js');
$tab_selected_tags = explode(',', $tags);
$selectiontags = " AND value IN ('" . implode("','", $tab_selected_tags) . "')";

// on recupere tous les tags existants
$sql = 'SELECT DISTINCT value FROM ' . $this->config['table_prefix'] . "triples WHERE property='http://outils-reseaux.org/_vocabulary/tag' ORDER BY value ASC";
$tab_tous_les_tags = $this->LoadAll($sql);
$tab_tag = [];
if (is_array($tab_tous_les_tags)) {
    foreach ($tab_tous_les_tags as $tag) {
        $tag['value'] = stripslashes($tag['value']);
        if (in_array($tag['value'], $tab_selected_tags)) {
            $tab_tag[] = '&nbsp;<a class="tag-label label label-primary label-active" href="' . $this->href('listpages', $this->GetPageTag(), 'tags=' . urlencode($tag['value'])) . '">' . $tag['value'] . '</a>' . "\n";
        } else {
            $tab_tag[] = '&nbsp;<a class="tag-label label label-info" href="' . $this->href('listpages', $this->GetPageTag(), 'tags=' . urlencode($tag['value'])) . '">' . $tag['value'] . '</a>' . "\n";
        }
    }
    $outputselecttag = '';
    if (!empty($tab_tag)) {
        $outputselecttag .= '<strong><i class="icon icon-tags"></i> ' . _t('TAGS_FILTER') . ' : </strong>';
        foreach ($tab_tag as $tag) {
            $outputselecttag .= $tag;
        }
    }
}

$text = '';
// affiche le resultat de la recherche
$resultat = $tagsManager->getPagesByTags($tags, $type, $nb, $tri, $template, $class, $lienedit);
if ($resultat) {
    $aclService = $this->services->get(YesWiki\Core\Service\AclService::class);
    $element = [];
    foreach ($resultat as $page) {
        if ($aclService->hasAccess('read', $page['tag'])) {
            $element[$page['tag']]['tagnames'] = '';
            $element[$page['tag']]['tagbadges'] = '';
            $element[$page['tag']]['body'] = $page['body'];
            $element[$page['tag']]['owner'] = $page['owner'];
            $element[$page['tag']]['user'] = $page['user'];
            $element[$page['tag']]['time'] = $page['time'];
            $element[$page['tag']]['title'] = get_title_from_body($page);
            $element[$page['tag']]['image'] = get_image_from_body($page);
            $element[$page['tag']]['desc'] = tokenTruncate(strip_tags($this->Format($page['body'], 'wakka', $page['tag'])), $nbcartrunc);
            $pagetags = $this->GetAllTriplesValues($page['tag'], 'http://outils-reseaux.org/_vocabulary/tag', '', '');
            foreach ($pagetags as $tag) {
                $element[$page['tag']]['tagnames'] .= sanitizeEntity($tag['value']) . ' ';
                $element[$page['tag']]['tagbadges'] .= '<span class="tag-label label label-primary">' . $tag['value'] . '</span>&nbsp;';
            }
        }
    }
    $text .= $this->render("@tags/$template", ['elements' => $element]);
    $nb_total = count($element);
} else {
    $nb_total = 0;
}

$output .= '<div class="alert alert-info">' . "\n";
if ($nb_total > 1) {
    $output .= _t('TAGS_TOTAL_NB_PAGES', ['nb_total' => $nb_total]);
} elseif ($nb_total == 1) {
    $output .= _t('TAGS_ONE_PAGE_FOUND');
} else {
    $output .= _t('TAGS_NO_PAGE');
}
$output .= (!empty($tab_selected_tags) ? ' ' . _t('TAGS_WITH_KEYWORD') . ' ' . implode(' ' . _t('TAGS_WITH_KEYWORD_SEPARATOR') . ' ', array_map(function ($tagName) {
    return '<span class="tag-label label label-info">' . $tagName . '</span>';
}, $tab_selected_tags)) : '') . '.';
$output .= $this->Format('{{rss tags="' . $tags . '" class="pull-right"}}') . "\n";
$output .= '</div>' . "\n" . $text;

echo $this->Header();
echo "<div class=\"page\">\n$output\n$outputselecttag\n<hr class=\"hr_clear\" />\n</div>\n";
echo $this->Footer();
