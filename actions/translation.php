<?php

// {{translation destination="xx"}} (formerly tools/lang): renders a flag link
// that switches the current page to the destination language via ?lang=xx

$destination = $this->GetParameter('destination');
if (empty($destination)) {
    echo _t('LANG_DESTINATION_REQUIRED');
}

$flagfile = 'styles/lang/flags/' . $destination . '.png';

if (!file_exists($flagfile)) {
    $img = $destination; // we are using the iso code if no flag available
} else {
    $img = '<img loading="lazy" src="' . $flagfile . '" title="' . $destination . '" alt="' . $destination . ' language">';
}

$wikireq = $_GET['wiki'] ?? null;

$currentMethod = empty($this->method) ? '' : '/' . $this->method;
$currentTag = (strpos($wikireq, '/') !== false)
        ? substr($wikireq, 0, -strlen($currentMethod))
        : $wikireq;

$queries = [];
parse_str($_SERVER['QUERY_STRING'], $queries);
unset($queries[$wikireq]);
unset($queries['wiki']);
$queries['lang'] = $destination;

// remove $_GET['lang'] because it is used by Href
if (isset($_GET['lang'])) {
    $previousLang = $_GET['lang'];
    unset($_GET['lang']);
}
// Todo : utiliser template
echo '<a href="' . $this->Href($wikireq === $currentTag ? '' : $this->method, $currentTag, $queries, false) . '">' . $img . '</a>';

if (isset($previousLang)) {
    $_GET['lang'] = $previousLang;
    unset($previousLang);
}
