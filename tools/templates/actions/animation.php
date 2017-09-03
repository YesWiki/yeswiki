<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$this->addCSSFile('tools/templates/presentation/styles/animate.css');
$this->addJavascriptFile('tools/templates/libs/vendor/wow.min.js');
$this->addJavascript('new WOW().init();');
