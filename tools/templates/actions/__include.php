<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$dblclic = $this->GetParameter('doubleclic');
$actif = $this->GetParameter('actif');
$pageincluded = $this->GetParameter('page');

// if metadata exists to change included page, we take the value of it
if (isset($this->metadatas[$pageincluded])) {
    $pageincluded = $this->metadatas[$pageincluded];
    $this->parameter["page"] = $pageincluded;
}
$clear = $this->GetParameter('clear');
$class = $this->GetParameter('class');
if (empty($class)) {
    $this->parameter['class'] = 'include';
    $class = 'include';
} else {
    $this->parameter['class'] = 'include '.$class;
    $class = 'include '.$class;
}
