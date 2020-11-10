<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

require_once 'tools/templates/libs/templates.functions.php';

$class = $this->getParameter('class');
echo show_form_theme_selector('selector', $class);
