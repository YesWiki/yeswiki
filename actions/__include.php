<?php

$dblclic = $this->GetParameter('doubleclic');
$actif = $this->GetParameter('actif');
$pageincluded = $this->GetParameter('page');

// if metadata exists to change included page, we take the value of it
if (isset($this->metadatas[$pageincluded])) {
    $oldpageincluded = $pageincluded;
    $pageincluded = $this->metadatas[$pageincluded];
    $this->parameter['page'] = $pageincluded;
    // to prevent errors in actions order in Performer
    if ($this->tag == trim($oldpageincluded)) { // case /attach/actions/___include before this
        // redo tools\attach\actions\__include.php without changing oldpage
        $this->tag = trim($pageincluded);
        $includedPage = $this->GetCachedPage($this->tag);
        $this->page = !empty($includedPage) ? $includedPage : $this->LoadPage($this->tag);
    }
}
$clear = $this->GetParameter('clear');
$class = $this->GetParameter('class');
if (empty($class)) {
    $this->parameter['class'] = 'include';
    $class = 'include';
} else {
    $this->parameter['class'] = 'include ' . $class;
    $class = 'include ' . $class;
}

// Page translation (formerly tools/lang's __include before-callback): filter the
// included page's body to the visitor's {{lang="xx"}} section and refresh the
// page cache so the include action below renders the filtered version
require_once YESWIKI_SOURCE_DIR . '/src/lang.functions.php';
$langIncludedPage = $this->LoadPage(trim($this->GetParameter('page')));
if (!empty($langIncludedPage['body'])) {
    $langFilteredBody = filterBodyByLanguage(
        $langIncludedPage['body'],
        $GLOBALS['prefered_language'],
        $this->config['default_language']
    );
    if ($langFilteredBody !== $langIncludedPage['body']) {
        $langIncludedPage['body'] = $langFilteredBody;
        // Hack : mise a jour du cache avec la nouvelle version.
        $this->CachePage($langIncludedPage);
    }
}
