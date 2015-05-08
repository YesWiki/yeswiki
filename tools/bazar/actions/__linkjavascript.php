<?php

if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

if ($this->GetMethod() == "show" || $this->GetMethod() == "iframe" || $this->GetMethod() == "edit") {
    $this->AddJavascriptFile('tools/bazar/libs/bazar.js');
}
