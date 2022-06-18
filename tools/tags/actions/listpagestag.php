<?php
/*
listpagestag.php

Copyright 2009  Florian SCHMITT
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

use YesWiki\Tags\Service\TagsManager;

if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

$tagsManager = $this->services->get(TagsManager::class);

include_once 'tools/tags/libs/tags.functions.php';
$nbcartrunc = 200;
$tags = $this->GetParameter('tags');
$type = $this->GetParameter('type');
// recuperation de tous les parametres
$lienedit = '';
$class = $this->GetParameter('class');
$nb = $this->GetParameter('nb');
$tri = $this->GetParameter('tri');
$template = $this->GetParameter('template');
if (empty($template)) {
    $template = 'pages_list.tpl.html';
}

$output = '';

// affiche le resultat de la recherche
$resultat = $tagsManager->getPagesByTags($tags, $type, $nb, $tri);
if ($resultat) {
    $nb_total = count($resultat);
    // affichage des resultats
    foreach ($resultat as $page) {
        // on inclue pas la page en elle meme, sinon boucle infinie
        if ($page['tag'] != $this->getPageTag()) {
            $element[$page['tag']]['tagnames'] = '';
            $element[$page['tag']]['tagbadges'] = '';
            $element[$page['tag']]['body'] = $page['body'];
            $element[$page['tag']]['owner'] = $page['owner'];
            $element[$page['tag']]['user'] = $page['user'];
            $element[$page['tag']]['time'] = $page['time'];
            $element[$page['tag']]['title'] = get_title_from_body($page);
            $element[$page['tag']]['image'] = get_image_from_body($page);
            $pagetags = $this->GetAllTriplesValues($page['tag'], 'http://outils-reseaux.org/_vocabulary/tag', '', '');
            foreach ($pagetags as $tag) {
                $element[$page['tag']]['tagnames'] .= sanitizeEntity($tag['value']).' ';
                $element[$page['tag']]['tagbadges'] .= '<span class="label label-info">'.$tag['value'].'</span>&nbsp;';
            }
        }
    }
    $output = $this->render("@tags/$template", ['elements' => $element]);
} else {
    $nb_total = 0;
}

$shownumberinfo = $this->GetParameter('shownumberinfo');
if (!empty($shownumberinfo) && $shownumberinfo == 1) {
    $info = '<div class="alert alert-info">'."\n";
    if ($nb_total > 1) {
        $info .= _t('TAGS_TOTAL_NB_PAGES', ['nb_total'=> $nb_total]);
    } elseif ($nb_total == 1) {
        $info .= _t('TAGS_ONE_PAGE_FOUND');
    } else {
        $info .= _t('TAGS_NO_PAGE');
    }
    $info .= (!empty($tags) ? ' ' . _t('TAGS_WITH_KEYWORD') . ' <span class="label label-info">'.$tags.'</span>' : '').'.';
    $info .= $this->Format('{{rss tags="'.$tags.'" class="pull-right"}}')."\n".'</div>'."\n";
    $output = $info.$output;
}

echo $output."\n";
