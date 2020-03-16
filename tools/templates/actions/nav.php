<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// classe css supplémentaire
$class = $this->GetParameter('class');
$class = ((!empty($class)) ? $class : 'nav nav-tabs');

// data attributes
$data = getDataParameter();
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
    foreach ($icons as $key => $icon){
        $icon = trim($icon);
        if (!empty($icon)) {
            // si le parametre contient des espaces, il s'agit d'une icone autre que celles par defaut de bootstrap
            if ( preg_match('/\s/', $icon) )
            {
                $icon = '<i class="'.$icon.'"></i>';
            }
            else
            {
                $icon = '<i class="icon-'.$icon.' fa fa-'.$icon.'"></i>';
            }
            if (!empty($text)) $icon = $icon.' ';
        }
        $icons[$key] = $icon;
    }
}

$listlinks = '';
foreach ($titles as $key => $title) {
    $url = $this->IsWikiName($links[$key], WN_CAMEL_CASE_EVOLVED) ? $this->href('', $links[$key]) : $links[$key];
    $listclass = ($url == $this->href('', $this->GetPageTag())) ? ' class="active"' : '';
    $listlinks .= '<li'.$listclass.'><a href="'.$url.'">'.$icons[$key].$title.'</a></li>'."\n";
}

$navID = uniqid('nav_');
$data = '';
if (is_array($data)) {
    foreach ($data as $key => $value) {
        $data .= ' data-'.$key.'="'.$value.'"';
    }
}

echo ' <!-- start of nav -->
        <ul class="'.$class.'" id="'.$navID.'" '.$data.'>'.$listlinks.'</ul>'."\n";
