<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

require_once 'tools/templates/libs/templates.functions.php';

$class = $this->getParameter('class');
if ($this->UserIsAdmin()
    && isset($_POST['action']) && ($_POST['action'] === 'setTemplate')
    ) {
    $this->Action('setwikidefaulttheme');
    // if not redirected by setwikidefaulttheme : redirect
    $this->Redirect($this->href("", $this->tag));
} else {
    echo show_form_theme_selector('selector', $class);
}
