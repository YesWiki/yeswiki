<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

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
