<?php

use YesWiki\Core\Service\LinkTracker;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// classe css supplémentaire
$class = $this->GetParameter('class');
$class = ((!empty($class)) ? $class : 'nav nav-tabs');

// data attributes
$data = $this->services->get(\YesWiki\Templates\Service\Utils::class)->getDataParameter();
$pagetag = $this->GetPageTag();

// liens
$links = $this->GetParameter('links');
if (!empty($links)) {
    $links = explode(',', $links);
    $links = array_map('trim', $links);
}

// titre des liens
$titles = $this->GetParameter('titles');
if (!empty($titles)) {
    $titles = explode(',', $titles);
    $titles = array_map('trim', $titles);
}

// icônes des titres
$icons = $this->GetParameter('icons');
if (!empty($icons)) {
    $icons = explode(',', $icons);
    foreach ($icons as $key => $icon) {
        $icon = trim($icon);
        if (!empty($icon)) {
            // si le parametre contient des espaces, il s'agit d'une icone autre que celles par defaut de bootstrap
            if (preg_match('/\s/', $icon)) {
                $icon = '<i class="' . $icon . '"></i>';
            } else {
                $icon = '<i class="icon-' . $icon . ' fa fa-' . $icon . '"></i>';
            }
            if (!empty($text)) {
                $icon = $icon . ' ';
            }
        }
        $icons[$key] = $icon;
    }
}

$hideIfNoAccess = $this->GetParameter('hideifnoaccess');
$listlinks = '';
foreach ($titles as $key => $title) {
    $haveAccess = true;
    if (empty($links[$key])) {
        $url = '';
    } else {
        $linkParts = $this->extractLinkParts($links[$key]);
        [$url, $method, $params] = ['', '', ''];
        if ($linkParts) {
            $this->services->get(LinkTracker::class)->forceAddIfNotIncluded($linkParts['tag']);
            $method = $linkParts['method'];
            $params = $linkParts['params'];
            $url = $this->href($method, $linkParts['tag'], $params);
            if ($hideIfNoAccess == 'true' && isset($linkParts['tag']) && !$GLOBALS['wiki']->HasAccess('read', $linkParts['tag'])) {
                $haveAccess = false;
            }
        } else {
            $url = $links[$key];
        }
    }
    // class="active" if the url have the same url than the current one (independently of the method and the params)
    if ($haveAccess) {
        $listclass = ($url == $this->href($method, $this->GetPageTag(), $params)) ? ' class="active"' : '';
        $listlinks .= '<li' . $listclass . '><a href="' . $url . '">'
            . (isset($icons[$key]) ? $icons[$key] : '')
            . $title . '</a></li>' . "\n";
    }
}

$navID = uniqid('nav_');
$data = '';
if (is_array($data)) {
    foreach ($data as $key => $value) {
        $data .= ' data-' . $key . '="' . $value . '"';
    }
}

if (!empty($listlinks)) {
    echo ' <!-- start of nav -->
        <nav><ul class="' . $class . '" id="' . $navID . '" ' . $data . '>' . $listlinks . '</ul></nav>' . "\n";
}