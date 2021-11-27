<?php

// TODO : a basculer dans __show.php
// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$destination = $this->GetParameter("destination");
if (empty($destination)) {
    echo _t(LANG_DESTINATION_REQUIRED);
}

$flagfile='tools/lang/presentation/images/'.$destination.'.png';

if (file_exists($flagfile)) {
    $wikireq = isset($_GET['wiki']) ? $_GET['wiki'] : null;

    $currentMethod = empty($this->method) ? '' : '/' . $this->method;
    $currentTag = (strpos($wikireq, '/') !== false)
        ? substr($wikireq, 0, -strlen($currentMethod))
        : $wikireq;

    $queries = [];
    parse_str($_SERVER['QUERY_STRING'], $queries);
    unset($queries[$wikireq]);
    unset($queries['wiki']);
    $queries['lang'] = $destination;
    $query = '';
    foreach ($queries as $key => $value) {
        $query .= '&'.$key."=".$value ;
    }
    $query = substr($query, 1); // remove first '&'

    // remove $_GET['lang'] because it is used by Href
    if (isset($_GET['lang'])) {
        $previousLang = $_GET['lang'];
        unset($_GET['lang']);
    }
    // Todo : utiliser template
    echo '<a href="'.$this->Href($wikireq === $currentTag ? '' : $this->method, $currentTag, $query, false).'">
        <img src="'.$flagfile.'" title="'.$destination.'" alt="Flag'.$destination.'"></img></a>';

    if (isset($previousLang)) {
        $_GET['lang'] = $previousLang;
        unset($previousLang);
    }
} else {
    echo _t(LANG_FLAG_FILE_MISSING);
}
