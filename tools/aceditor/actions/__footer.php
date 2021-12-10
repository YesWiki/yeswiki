<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$this->AddJavascriptFile('tools/aceditor/presentation/javascripts/ace-lib.js');
$this->AddJavascriptFile('tools/aceditor/presentation/javascripts/mode-html.js');
$this->AddJavascriptFile('tools/aceditor/presentation/javascripts/aceditor.js');
$this->AddCSSFile('tools/aceditor/presentation/styles/aceditor.css');
