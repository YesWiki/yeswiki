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

$wikireq = $_REQUEST['wiki'];
// remove leading slash
$wikireq = preg_replace("/^\//", "", $wikireq);
// split into page/method, checking wiki name & method name (XSS proof)
if (preg_match('`^' . '(' . "[A-Za-z0-9]+" . ')/(' . "[A-Za-z0-9_-]" . '*)' . '$`', $wikireq, $matches)) {
    list(, $PageTag, $method) = $matches;
} elseif (preg_match('`^' . "[A-Za-z0-9]+" . '$`', $wikireq)) {
    $PageTag = $wikireq;
}
// Todo : utiliser template

$flagfile='tools/lang/presentation/images/'.$destination.'.png';

if (file_exists($flagfile)) {
    echo '<a href="wakka.php?wiki='.$PageTag.'&lang='.$destination.'">
        <img src="'.$flagfile.'" title="'.$destination.'" alt="Flag'.$destination.'"></img></a>';
} else {
    echo _t(LANG_FLAG_FILE_MISSING);
}
