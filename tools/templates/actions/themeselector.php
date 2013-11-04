<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

$class = $this->getParameter('class');
if (empty($class)) $class = 'form-horizontal';
echo show_form_theme_selector('selector', $class);

?>
