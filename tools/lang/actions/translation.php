<?php

// TODO : a basculer dans __show.php

$destination = $this->GetParameter('destination');
if (empty($destination)) {
    echo _t('LANG_DESTINATION_REQUIRED');
}

$flagfile = 'tools/lang/presentation/images/' . $destination . '.png';

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
echo '<a href="' . $this->Href(
    $wikireq === $currentTag ? '' : $this->method,
    $currentTag,
    $queries,
    false
) . '">';
if (file_exists($flagfile)) {
    echo '<img loading="lazy" src="' . $flagfile . '" title="' . $destination . '" alt="' . $destination . ' language"></img>';
} else {
    echo $destination;
}
echo '</a>';

if (isset($previousLang)) {
    $_GET['lang'] = $previousLang;
    unset($previousLang);
}
