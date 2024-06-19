<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}
include_once 'tools/tags/libs/tags.functions.php';
$nbcartrunc = 200;
$output = '';
$class = $this->GetParameter('class');
$pages = $this->GetParameter('pages');

if (empty($pages)) {
    $output .= '<div class="alert alert-danger"><strong>' . _t('TAGS_ACTION_INCLUDEPAGES') . '</strong> : ' . _t('TAGS_NO_PARAM_PAGES') . '</div>' . "\n";
} else {
    $template = $this->GetParameter('template');
    if (empty($template)) {
        $template = 'pages_list.tpl.html';
    }

    $resultat = explode(',', $pages);
    foreach ($resultat as $page) {
        $page = $this->LoadPage(trim($page));
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
            $element[$page['tag']]['tagbadges'] .= '<span class="label label-info">' . $tag['value'] . '</span>&nbsp;';
        }
    }

    $output .= $this->render("@tags/$template", ['elements' => $element]);
}

if (empty($class)) {
    echo $output . "\n";
} else {
    echo '<div class="' . $class . '">' . "\n" . $output . "\n" . '</div>' . "\n";
}
