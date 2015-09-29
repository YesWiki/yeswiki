<?php

if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

// cette action n'est plus appelée, c'est présent pour les vieux themes de yeswiki anacoluthe 
if ($this->GetMethod() == "show" || $this->GetMethod() == "iframe" || $this->GetMethod() == "edit") {
    $this->AddJavascriptFile('tools/bazar/libs/bazar.js');
}
