<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// classe css supplémentaire
$class = $this->GetParameter('class');
$class = ((!empty($class)) ? $class : 'nav nav-pills');

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

$listlinks = '';
foreach ($titles as $key => $title) {
    $url = $this->IsWikiName($links[$key]) ? $this->href('', $links[$key]) : $links[$key];
    $listclass = ($url == $this->href('', $this->GetPageTag())) ? ' class="active"' : '';
    $listlinks .= '<li'.$listclass.'><a href="'.$url.'">'.$title.'</a></li>'."\n";
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
