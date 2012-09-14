<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$dblclic = $this->GetParameter('doubleclic');
$actif = $this->GetParameter('actif');
$pageincluded = $this->GetParameter('page');
$clear = $this->GetParameter('clear');
$class = $this->GetParameter('class');
if (empty($class)) {
    $this->parameter['class'] = 'include';
    $class = 'include';
}
?>
